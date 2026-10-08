<?php

namespace App\Http\Controllers\Commerce;

use App\Events\CommerceCheckoutSessionLoading;
use App\Http\Controllers\Controller;
use App\Models\CommerceCheckoutSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Plugins\Commerce\CommerceCheckoutContextRegistry;
use App\Plugins\PluginCheckoutExtensionRegistry;
use App\Plugins\PluginHookBus;
use App\Services\CheckoutAbuseGuard;
use App\Services\Commerce\CommerceCheckoutPaymentService;
use App\Services\Storefront\StoreCatalogService;
use App\Services\Storefront\StoreThemeService;
use App\Support\CheckoutCardCredentialsPayload;
use App\Support\CheckoutPaymentMethodsBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommerceCheckoutController extends Controller
{
    public function __construct(
        protected CommerceCheckoutPaymentService $payments,
    ) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $session = CommerceCheckoutSession::where('session_token', $token)->first();
        if (! $session || $session->isExpired()) {
            abort(404, 'Sessão inválida ou expirada.');
        }

        $order = Order::with('product')->find($session->order_id);
        if (! $order || $order->status !== 'pending') {
            abort(404, 'Pedido indisponível.');
        }

        $tenantId = (int) $session->tenant_id;
        $productModel = $order->product;
        $gatewayConfig = CommerceCheckoutContextRegistry::resolveGatewayConfig($order);
        $config = is_array($productModel?->checkout_config) ? $productModel->checkout_config : [];
        $productPg = is_array($config['payment_gateways'] ?? null) ? $config['payment_gateways'] : [];

        $sessionMeta = is_array($session->metadata) ? $session->metadata : [];
        $orderMeta = is_array($order->metadata) ? $order->metadata : [];
        $isStorefront = ! empty($sessionMeta['storefront_checkout'])
            || ! empty($orderMeta['storefront_checkout'])
            || (($sessionMeta['source'] ?? '') === 'commerce_cart');

        if ($isStorefront) {
            $storePg = app(StoreThemeService::class)->resolveStorePaymentGateways($tenantId);
            $pg = $gatewayConfig ?? $storePg;
        } else {
            $pg = $gatewayConfig ?? $productPg;
        }
        if (! is_array($pg)) {
            $pg = [];
        }

        $checkoutPaymentMethods = CheckoutPaymentMethodsBuilder::build($tenantId, $pg, null);
        $availableMethods = CheckoutPaymentMethodsBuilder::methodIds($checkoutPaymentMethods);
        if ($availableMethods === []) {
            if ($isStorefront) {
                return redirect('/checkout/entrega')
                    ->with('error', 'Nenhum método de pagamento disponível. Configure em Configurações → Pagamento e conecte um gateway em Integrações.');
            }
            abort(422, 'Nenhum método de pagamento configurado.');
        }

        $cardCredentials = CheckoutCardCredentialsPayload::forMethods($tenantId, $checkoutPaymentMethods);
        $customer = is_array($session->customer) ? $session->customer : [];
        $lineItems = is_array($session->line_items) ? $session->line_items : [];

        $registryLineItems = CommerceCheckoutContextRegistry::resolveLineItems($order);
        if ($registryLineItems !== null && $registryLineItems !== []) {
            $lineItems = $registryLineItems;
        }

        $currenciesRaw = Setting::get('currencies', null, $tenantId);
        $currencies = $currenciesRaw
            ? (is_string($currenciesRaw) ? json_decode($currenciesRaw, true) : $currenciesRaw)
            : config('products.currencies');
        $currencies = is_array($currencies) ? $currencies : config('products.currencies');

        $productName = CommerceCheckoutContextRegistry::resolveOrderLabel($order);
        if ($productName === null || $productName === '') {
            $productName = count($lineItems) > 1
                ? count($lineItems).' itens'
                : ($lineItems[0]['name'] ?? $productModel?->name ?? 'Pedido');
        }

        $paymentSummary = CommerceCheckoutContextRegistry::resolvePaymentSummary($order, $session) ?? [];

        $shippingQuote = $sessionMeta['shipping_quote'] ?? ($orderMeta['shipping_quote'] ?? null);
        $shippingAddress = $sessionMeta['shipping_address'] ?? (is_array($order->shipping_address) ? $order->shipping_address : null);
        $shippingAmount = (float) ($sessionMeta['shipping_amount'] ?? $order->shipping_amount ?? 0);
        $subtotal = (float) ($sessionMeta['subtotal'] ?? ((float) $session->amount - $shippingAmount));

        // Enrich line items with images when missing
        $enrichedLines = [];
        foreach ($lineItems as $item) {
            $row = is_array($item) ? $item : [];
            if (empty($row['image_url']) && ! empty($row['product_id'])) {
                $p = Product::find($row['product_id']);
                $row['image_url'] = $p?->galleryUrls()[0] ?? null;
            }
            $enrichedLines[] = $row;
        }
        $lineItems = $enrichedLines;

        $payload = new \ArrayObject([
            'session_token' => $token,
            'commerce_checkout' => true,
            'storefront_checkout' => $isStorefront,
            'commerce_line_items' => $lineItems,
            'app_name' => config('app.name'),
            'app_logo_url' => null,
            'app_sidebar_bg_color' => '#18181b',
            'conversion_pixels' => $productModel
                ? (is_array($productModel->conversion_pixels) ? $productModel->conversion_pixels : Product::defaultConversionPixels())
                : Product::defaultConversionPixels(),
            'customer_email' => $customer['email'] ?? null,
            'customer_name' => $customer['name'] ?? null,
            'customer_cpf' => $customer['cpf'] ?? null,
            'customer_phone' => $customer['phone'] ?? null,
            'amount' => (float) $session->amount,
            'currency' => $session->currency ?? 'BRL',
            'currencies' => $currencies,
            'product_name' => $productName,
            'product_image_url' => null,
            'available_methods' => $availableMethods,
            'checkout_payment_methods' => $checkoutPaymentMethods,
            'return_url' => $isStorefront ? '/checkout/entrega' : null,
            'card_gateway_slug' => $cardCredentials['card_gateway_slug'],
            'card_payee_code' => $cardCredentials['card_payee_code'],
            'card_efi_sandbox' => $cardCredentials['card_efi_sandbox'],
            'card_stripe_publishable_key' => $cardCredentials['card_stripe_publishable_key'],
            'card_stripe_sandbox' => $cardCredentials['card_stripe_sandbox'],
            'card_stripe_link_enabled' => $cardCredentials['card_stripe_link_enabled'],
            'card_mercadopago_public_key' => $cardCredentials['card_mercadopago_public_key'],
            'card_mercadopago_sandbox' => $cardCredentials['card_mercadopago_sandbox'],
            'card_pagarme_public_key' => $cardCredentials['card_pagarme_public_key'],
            'card_pagarme_api_base_url' => $cardCredentials['card_pagarme_api_base_url'],
            'card_paypal_client_id' => $cardCredentials['card_paypal_client_id'] ?? '',
            'card_paypal_sandbox' => $cardCredentials['card_paypal_sandbox'] ?? false,
            'card_paypal_checkout_mode' => $cardCredentials['card_paypal_checkout_mode'] ?? 'auto',
            'card_gateway_keys' => $cardCredentials['card_gateway_keys'],
            'payment_summary' => $paymentSummary,
            'checkout_extra' => [],
            'plugin_checkout_extensions' => PluginCheckoutExtensionRegistry::activeForOrder($order, 'commerce'),
            'order_summary' => [
                'lines' => array_map(static function ($row) {
                    return [
                        'id' => $row['product_id'] ?? null,
                        'name' => $row['name'] ?? 'Produto',
                        'quantity' => (int) ($row['quantity'] ?? 1),
                        'amount' => (float) ($row['amount'] ?? 0),
                        'unit_amount' => (float) ($row['unit_amount'] ?? 0),
                        'image_url' => $row['image_url'] ?? null,
                    ];
                }, $lineItems),
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingAmount,
                'shipping_label' => is_array($shippingQuote) ? ($shippingQuote['name'] ?? null) : null,
                'total' => (float) $session->amount,
            ],
            'shipping_summary' => [
                'email' => $customer['email'] ?? ($shippingAddress['email'] ?? null),
                'quote' => $shippingQuote,
                'address' => $shippingAddress,
            ],
        ]);

        if ($isStorefront) {
            $themeService = app(StoreThemeService::class);
            $catalog = app(StoreCatalogService::class);
            $payload['storeTheme'] = $themeService->getTheme($tenantId);
            $payload['menuCategories'] = $catalog->serializeMenuTree($tenantId);
            $payload['cartCount'] = 0;
            $payload['isPreview'] = false;
        }

        event(new CommerceCheckoutSessionLoading($session, $order, $payload));

        $renderPayload = $payload->getArrayCopy();
        if ($productModel) {
            $renderPayload = PluginHookBus::applyFilters('checkout.payload', $renderPayload, $productModel, $request);
        }

        $page = $isStorefront ? 'Storefront/Checkout/Payment' : 'ApiCheckout/Show';

        return Inertia::render($page, $renderPayload);
    }

    public function process(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $rules = [
            'session_token' => ['required', 'string', 'max:64'],
            'payment_method' => ['required', 'string', 'in:pix,pix_auto,boleto,card'],
            'plugin_checkout_data' => ['nullable', 'string', 'max:65535'],
            'inline' => ['nullable', 'boolean'],
        ];
        if ($request->input('payment_method') === 'card') {
            $rules['payment_token'] = ['required', 'string', 'max:10000'];
            $rules['card_mask'] = ['nullable', 'string', 'max:32'];
        }
        $validated = $request->validate($rules);

        $session = CommerceCheckoutSession::where('session_token', $validated['session_token'])->first();
        if (! $session) {
            if ($request->boolean('inline') || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'Sessão inválida.'], 422);
            }

            return redirect()->back()->with('error', 'Sessão inválida.');
        }

        return $this->payments->process($request, $session, $validated);
    }
}
