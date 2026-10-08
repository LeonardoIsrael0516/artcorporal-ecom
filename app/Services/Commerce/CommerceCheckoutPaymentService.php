<?php

namespace App\Services\Commerce;

use App\Events\BoletoGenerated;
use App\Events\CheckoutBeforeProcess;
use App\Events\OrderCompleted;
use App\Events\OrderPending;
use App\Events\PixGenerated;
use App\Models\CommerceCart;
use App\Models\CommerceCheckoutSession;
use App\Models\Order;
use App\Models\Product;
use App\Plugins\Commerce\CommerceCheckoutContextRegistry;
use App\Plugins\PluginCheckoutExtensionRegistry;
use App\Plugins\PluginHookBus;
use App\Services\CheckoutAbuseGuard;
use App\Services\PaymentService;
use App\Support\FakeConsumerData;
use App\Support\PixCheckoutDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommerceCheckoutPaymentService
{
    public function process(Request $request, CommerceCheckoutSession $session, array $validated): RedirectResponse|JsonResponse
    {
        $inline = $request->boolean('inline') || $request->wantsJson();

        $fail = function (string $message) use ($inline): RedirectResponse|JsonResponse {
            if ($inline) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->back()->with('error', $message);
        };

        if ($session->isExpired()) {
            return $fail('Sessão inválida ou expirada.');
        }

        $order = Order::with('product', 'orderItems')->find($session->order_id);
        if (! $order || $order->status !== 'pending') {
            return $fail('Pedido indisponível.');
        }

        $tenantId = (int) $session->tenant_id;
        $product = $order->product;
        $isPluginCheckout = CommerceCheckoutContextRegistry::supports($order);
        if ($product && ! $product->is_active && ! $isPluginCheckout) {
            return $fail('Produto indisponível.');
        }

        $registryGateway = CommerceCheckoutContextRegistry::resolveGatewayConfig($order);
        $config = is_array($product?->checkout_config) ? $product->checkout_config : [];
        $productPg = is_array($config['payment_gateways'] ?? null) ? $config['payment_gateways'] : [];
        $orderMeta = is_array($order->metadata) ? $order->metadata : [];
        $sessionMeta = is_array($session->metadata) ? $session->metadata : [];
        $isStorefront = ! empty($orderMeta['storefront_checkout'])
            || ! empty($sessionMeta['storefront_checkout'])
            || (($sessionMeta['source'] ?? '') === 'commerce_cart');
        $gatewayConfig = $registryGateway
            ?? ($isStorefront
                ? app(\App\Services\Storefront\StoreThemeService::class)->resolveStorePaymentGateways($tenantId)
                : $productPg);
        if (! is_array($gatewayConfig)) {
            $gatewayConfig = [];
        }

        $method = $validated['payment_method'];
        $pg = $gatewayConfig;
        $methodAvailable = $this->methodAvailable($tenantId, $pg, $method);
        if (! $methodAvailable) {
            return $fail('Método de pagamento não disponível.');
        }

        $customer = is_array($session->customer) ? $session->customer : [];
        $email = (string) ($customer['email'] ?? $order->email ?? '');
        $name = trim((string) ($customer['name'] ?? '')) ?: $email;
        $rawDoc = preg_replace('/\D/', '', (string) ($customer['cpf'] ?? $order->cpf ?? ''));
        $fake = FakeConsumerData::getForGateway($session->id);
        $consumer = [
            'name' => $name ?: $fake['name'],
            'document' => strlen($rawDoc) >= 11 ? $rawDoc : $fake['document'],
            'email' => $email,
            'phone' => trim((string) ($customer['phone'] ?? $order->phone ?? '')),
        ];

        $amount = (float) $order->amount;
        $paymentService = app(PaymentService::class);

        if ($product) {
            $request->merge(['email' => $email, 'product_id' => $product->id, 'payment_method' => $method]);
            app(CheckoutAbuseGuard::class)->assertCanCreateCheckout($request, $product);
        }

        $pluginCheckoutData = PluginCheckoutExtensionRegistry::decodeCheckoutDataFromRequest($request);
        if ($product) {
            $beforeProcess = new CheckoutBeforeProcess($product, $validated, null, $pluginCheckoutData);
            PluginCheckoutExtensionRegistry::invokeProcessHandlers($product, $validated, $pluginCheckoutData, $beforeProcess);
            PluginHookBus::doAction('checkout.before_process', $beforeProcess, $product, $validated, $pluginCheckoutData);
            event($beforeProcess);
            if ($beforeProcess->abort !== null && $beforeProcess->abort !== '') {
                return $fail((string) $beforeProcess->abort);
            }
        }

        if ($method === 'pix') {
            try {
                event(new OrderPending($order));
                $result = $paymentService->createPixPayment($order, $product, $consumer, $gatewayConfig);
                event(new PixGenerated($order, [
                    'qrcode' => $result['qrcode'] ?? null,
                    'copy_paste' => $result['copy_paste'] ?? null,
                    'transaction_id' => $result['transaction_id'] ?? null,
                ]));
                $pixToken = PixCheckoutDisplay::persistAndStoreSession($order, $result, [
                    'amount' => $amount,
                    'product_name' => $this->productLabel($session),
                    'redirect_after_purchase' => route('checkout.thank-you', ['order_id' => $order->id]),
                    'customer_name' => $name,
                    'customer_email' => $email,
                    'customer_phone' => $consumer['phone'] ?? null,
                    'storefront_checkout' => true,
                    'checkout_slug' => null,
                ]);

                $this->clearStorefrontCartAfterCheckout($session);

                if ($inline && $isStorefront) {
                    return response()->json([
                        'ok' => true,
                        'method' => 'pix',
                        'token' => $pixToken,
                        'order_id' => $order->id,
                        'qrcode' => $result['qrcode'] ?? null,
                        'copy_paste' => $result['copy_paste'] ?? null,
                        'amount' => $amount,
                        'amount_formatted' => 'R$ '.number_format($amount, 2, ',', '.'),
                        'created_at' => time(),
                        'expiry_seconds' => PixCheckoutDisplay::EXPIRY_SECONDS,
                        'redirect_after_purchase' => route('checkout.thank-you', ['order_id' => $order->id]),
                        'status_url' => url('/checkout/order-status?token='.$pixToken),
                    ]);
                }

                return redirect()->route('checkout.pix', ['token' => $pixToken]);
            } catch (\Throwable $e) {
                if ($inline && $isStorefront) {
                    return response()->json(['ok' => false, 'message' => $e->getMessage() ?: 'Não foi possível gerar o PIX.'], 422);
                }

                return redirect()->back()->with('error', $e->getMessage() ?: 'Não foi possível gerar o PIX.');
            }
        }

        if ($method === 'boleto') {
            try {
                event(new OrderPending($order));
                $result = $paymentService->createBoletoPayment($order, $product, $consumer, $gatewayConfig);
                event(new BoletoGenerated($order, [
                    'amount' => $result['amount'] ?? $amount,
                    'expire_at' => $result['expire_at'] ?? null,
                    'barcode' => $result['barcode'] ?? null,
                    'pdf_url' => $result['pdf_url'] ?? null,
                ]));
                $boletoToken = Str::random(32);
                session()->put('boleto_display.'.$boletoToken, [
                    'order_id' => $order->id,
                    'amount_formatted' => 'R$ '.number_format($result['amount'] ?? $amount, 2, ',', '.'),
                    'expire_at' => $result['expire_at'] ?? null,
                    'barcode' => $result['barcode'] ?? '',
                    'pdf_url' => $result['pdf_url'] ?? null,
                    'product_name' => $this->productLabel($session),
                    'redirect_after_purchase' => route('checkout.thank-you', ['order_id' => $order->id]),
                    'customer_name' => $name,
                    'customer_email' => $email,
                    'customer_phone' => $consumer['phone'] ?? null,
                    'storefront_checkout' => true,
                ]);

                $this->clearStorefrontCartAfterCheckout($session);

                if ($inline && $isStorefront) {
                    return response()->json([
                        'ok' => true,
                        'method' => 'boleto',
                        'token' => $boletoToken,
                        'order_id' => $order->id,
                        'barcode' => $result['barcode'] ?? '',
                        'pdf_url' => $result['pdf_url'] ?? null,
                        'expire_at' => $result['expire_at'] ?? null,
                        'amount' => $result['amount'] ?? $amount,
                        'amount_formatted' => 'R$ '.number_format($result['amount'] ?? $amount, 2, ',', '.'),
                        'redirect_after_purchase' => route('checkout.thank-you', ['order_id' => $order->id]),
                        'status_url' => url('/checkout/order-status?token='.$boletoToken),
                    ]);
                }

                return redirect()->route('checkout.boleto', ['token' => $boletoToken]);
            } catch (\Throwable $e) {
                if ($inline && $isStorefront) {
                    return response()->json(['ok' => false, 'message' => $e->getMessage() ?: 'Não foi possível gerar o boleto.'], 422);
                }

                return redirect()->back()->with('error', $e->getMessage() ?: 'Não foi possível gerar o boleto.');
            }
        }

        if ($method === 'card') {
            $card = [
                'payment_token' => $validated['payment_token'],
                'card_mask' => $validated['card_mask'] ?? null,
                'return_url' => route('checkout.thank-you', ['order_id' => $order->id]),
            ];
            try {
                event(new OrderPending($order));
                $cardGatewayConfig = $gatewayConfig;
                $cardGatewayConfig['card_redundancy'] = [];
                $result = $paymentService->createCardPayment($order, $product, $consumer, $card, $cardGatewayConfig);
                $status = strtolower((string) ($result['status'] ?? 'pending'));
                $isApproved = in_array($status, ['paid', 'settled', 'approved', 'completed'], true);
                if ($isApproved) {
                    $order->update(['status' => 'completed']);
                    event(new OrderCompleted($order));
                    $this->clearStorefrontCartAfterCheckout($session);

                    return redirect()->route('checkout.thank-you', ['order_id' => $order->id]);
                }

                return redirect()->back()->with('error', 'Pagamento recusado ou pendente.');
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: 'Não foi possível processar o cartão.');
            }
        }

        return redirect()->back()->with('error', 'Método não suportado.');
    }

    /**
     * Esvazia o carrinho da loja após pedido iniciado/pago (evita itens “fantasma”).
     */
    private function clearStorefrontCartAfterCheckout(CommerceCheckoutSession $session): void
    {
        try {
            $cartId = $session->commerce_cart_id;
            $meta = is_array($session->metadata) ? $session->metadata : [];
            if (! $cartId && ! empty($meta['commerce_cart_id'])) {
                $cartId = (int) $meta['commerce_cart_id'];
            }
            $order = Order::find($session->order_id);
            $orderMeta = is_array($order?->metadata) ? $order->metadata : [];
            if (! $cartId && ! empty($orderMeta['commerce_cart_id'])) {
                $cartId = (int) $orderMeta['commerce_cart_id'];
            }
            if (! $cartId) {
                return;
            }

            $cart = CommerceCart::with('lines')->find($cartId);
            if (! $cart) {
                return;
            }

            app(CommerceCartService::class)->clear($cart);
            $cart->update([
                'shipping_quote' => null,
                'shipping_address' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('clearStorefrontCartAfterCheckout failed', [
                'session_id' => $session->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function productLabel(CommerceCheckoutSession $session): string
    {
        $order = Order::find($session->order_id);
        if ($order) {
            $label = CommerceCheckoutContextRegistry::resolveOrderLabel($order);
            if ($label !== null && $label !== '') {
                return $label;
            }
        }

        $items = is_array($session->line_items) ? $session->line_items : [];
        if (count($items) > 1) {
            return count($items).' itens';
        }

        return (string) ($items[0]['name'] ?? 'Pedido');
    }

    /**
     * @param  array<string, mixed>  $pg
     */
    private function methodAvailable(int $tenantId, array $pg, string $method): bool
    {
        $methods = \App\Support\CheckoutPaymentMethodsBuilder::build($tenantId, $pg, null);
        $ids = \App\Support\CheckoutPaymentMethodsBuilder::methodIds($methods);

        return in_array($method, $ids, true);
    }
}
