<?php

namespace App\Services\Commerce;

use App\Events\CheckoutBeforeProcess;
use App\Models\CommerceCart;
use App\Models\CommerceCheckoutSession;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Plugins\Commerce\PluginCommercePricing;
use App\Plugins\Commerce\PluginTenantGuard;
use App\Plugins\PluginCheckoutExtensionRegistry;
use App\Plugins\PluginHookBus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CommerceCheckoutBridge
{
    /**
     * @param  array<string, mixed>  $customer  email (required), name?, cpf?, phone?, login?, save_address?
     * @return array{checkout_url: string, order_id: int, session_token: string}
     */
    public function startFromCart(CommerceCart $cart, array $customer): array
    {
        $cart->loadMissing(['lines.product']);
        if ($cart->isExpired()) {
            abort(410, 'Carrinho expirado.');
        }
        if ($cart->lines->isEmpty()) {
            abort(422, 'Carrinho vazio.');
        }

        $shippingQuote = is_array($cart->shipping_quote) ? $cart->shipping_quote : null;
        $shippingAddress = is_array($cart->shipping_address) ? $cart->shipping_address : null;
        if (! $shippingQuote || empty($shippingQuote['id'])) {
            abort(422, 'Selecione uma opção de frete.');
        }
        if (! $shippingAddress || empty($shippingAddress['cep']) || empty($shippingAddress['street']) || empty($shippingAddress['city']) || empty($shippingAddress['state'])) {
            abort(422, 'Preencha o endereço de entrega.');
        }

        $tenantId = (int) $cart->tenant_id;
        PluginTenantGuard::assertTenantId($tenantId);

        $email = trim((string) ($customer['email'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            abort(422, 'E-mail do cliente obrigatório.');
        }

        $currency = null;
        $total = 0.0;
        $anchorProduct = null;
        $lineSnapshots = [];

        foreach ($cart->lines as $line) {
            $product = Product::forTenant($tenantId)->where('id', $line->product_id)->where('is_active', true)->first();
            if (! $product) {
                abort(422, 'Produto indisponível no carrinho.');
            }
            $offer = $line->product_offer_id
                ? ProductOffer::where('id', $line->product_offer_id)->where('product_id', $product->id)->first()
                : null;
            $plan = $line->subscription_plan_id
                ? SubscriptionPlan::where('id', $line->subscription_plan_id)->where('product_id', $product->id)->first()
                : null;
            $lineCurrency = PluginCommercePricing::currency($product, $offer, $plan);
            if ($currency === null) {
                $currency = $lineCurrency;
            } elseif ($lineCurrency !== $currency) {
                abort(422, 'Itens com moedas diferentes não podem ser pagos juntos.');
            }
            $lineTotal = (float) $line->unit_amount * (int) $line->quantity;
            $total += $lineTotal;
            if ($anchorProduct === null) {
                $anchorProduct = $product;
            }
            $lineSnapshots[] = [
                'product_id' => $product->id,
                'product_offer_id' => $offer?->id,
                'subscription_plan_id' => $plan?->id,
                'quantity' => (int) $line->quantity,
                'unit_amount' => (float) $line->unit_amount,
                'amount' => $lineTotal,
                'name' => $product->name,
                'image_url' => $product->galleryUrls()[0] ?? null,
            ];
        }

        $shippingAmount = (float) ($shippingQuote['price'] ?? 0);
        if ($shippingAmount > 0) {
            $total += $shippingAmount;
        }

        $name = trim((string) ($customer['name'] ?? '')) ?: ($shippingAddress['recipient_name'] ?? $email);
        $wasNew = ! User::query()->where('email', $email)->exists();
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => bcrypt(Str::random(32)),
                'role' => User::ROLE_ALUNO,
                'tenant_id' => $tenantId,
            ]
        );

        if (! $wasNew && $user->name !== $name && $name !== $email) {
            $user->name = $name;
            $user->save();
        }

        if (! empty($customer['login'])) {
            $current = Auth::user();
            $shouldLogin = ! $current
                || ($current->id !== $user->id && $current->role === User::ROLE_ALUNO);
            if ($shouldLogin) {
                Auth::login($user);
                if (request()->hasSession()) {
                    request()->session()->regenerate();
                }
            }
        }

        if (! empty($customer['save_address']) && $user->role === User::ROLE_ALUNO) {
            $this->upsertCustomerAddress($user, $shippingAddress, $customer);
        }

        $cartValidated = [
            'email' => $email,
            'commerce_cart_id' => $cart->id,
        ];
        $pluginCheckoutData = PluginCheckoutExtensionRegistry::decodeCheckoutDataFromRequest(request());
        $before = new CheckoutBeforeProcess($anchorProduct, $cartValidated, $cart, $pluginCheckoutData);
        PluginCheckoutExtensionRegistry::invokeProcessHandlers($anchorProduct, $cartValidated, $pluginCheckoutData, $before);
        PluginHookBus::doAction('checkout.before_process', $before, $anchorProduct, $cartValidated, $pluginCheckoutData);
        event($before);
        if ($before->abort !== null && $before->abort !== '') {
            abort(422, $before->abort);
        }

        $firstLine = $cart->lines->first();
        $order = Order::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'product_id' => $firstLine->product_id,
            'product_offer_id' => $firstLine->product_offer_id,
            'subscription_plan_id' => $firstLine->subscription_plan_id,
            'amount' => round($total, 2),
            'currency' => $currency ?? 'BRL',
            'email' => $email,
            'cpf' => $customer['cpf'] ?? ($shippingAddress['cpf'] ?? null),
            'phone' => $customer['phone'] ?? ($shippingAddress['phone'] ?? null),
            'status' => 'pending',
            'shipping_amount' => $shippingAmount > 0 ? round($shippingAmount, 2) : null,
            'shipping_service' => $shippingQuote['name'] ?? null,
            'shipping_provider' => $shippingQuote['provider'] ?? null,
            'shipping_days' => isset($shippingQuote['days']) ? (int) $shippingQuote['days'] : null,
            'shipping_address' => $shippingAddress,
            'metadata' => [
                'commerce_cart_id' => $cart->id,
                'commerce_multi_line' => true,
                'storefront_checkout' => true,
                'customer_name' => $name,
                'shipping_quote' => $shippingQuote,
                'line_items' => $lineSnapshots,
            ],
        ]);

        $pos = 0;
        foreach ($cart->lines as $line) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $line->product_id,
                'product_offer_id' => $line->product_offer_id,
                'subscription_plan_id' => $line->subscription_plan_id,
                'amount' => round((float) $line->unit_amount * (int) $line->quantity, 2),
                'position' => $pos++,
            ]);
        }

        $token = Str::random(48);
        $expires = now()->addHours((int) config('plugins.commerce_checkout_ttl_hours', 2));
        CommerceCheckoutSession::create([
            'tenant_id' => $tenantId,
            'commerce_cart_id' => $cart->id,
            'session_token' => $token,
            'order_id' => $order->id,
            'amount' => $order->amount,
            'currency' => $currency ?? 'BRL',
            'customer' => [
                'email' => $email,
                'name' => $name,
                'cpf' => $customer['cpf'] ?? ($shippingAddress['cpf'] ?? null),
                'phone' => $customer['phone'] ?? ($shippingAddress['phone'] ?? null),
            ],
            'line_items' => $lineSnapshots,
            'metadata' => [
                'source' => 'commerce_cart',
                'storefront_checkout' => true,
                'shipping_quote' => $shippingQuote,
                'shipping_address' => $shippingAddress,
                'subtotal' => round($total - $shippingAmount, 2),
                'shipping_amount' => round($shippingAmount, 2),
            ],
            'expires_at' => $expires,
        ]);

        return [
            'checkout_url' => url('/commerce/checkout/'.$token),
            'order_id' => (int) $order->id,
            'session_token' => $token,
        ];
    }

    /**
     * @param  array<string, mixed>  $shippingAddress
     * @param  array<string, mixed>  $customer
     */
    protected function upsertCustomerAddress(User $user, array $shippingAddress, array $customer): void
    {
        $cep = preg_replace('/\D/', '', (string) ($shippingAddress['cep'] ?? ''));
        if ($cep === '') {
            return;
        }

        $payload = [
            'label' => 'Principal',
            'recipient_name' => $shippingAddress['recipient_name'] ?? $customer['name'] ?? $user->name,
            'phone' => $shippingAddress['phone'] ?? $customer['phone'] ?? null,
            'cep' => $cep,
            'street' => $shippingAddress['street'] ?? '',
            'number' => $shippingAddress['number'] ?? null,
            'complement' => $shippingAddress['complement'] ?? null,
            'district' => $shippingAddress['district'] ?? null,
            'city' => $shippingAddress['city'] ?? '',
            'state' => strtoupper((string) ($shippingAddress['state'] ?? '')),
            'is_default' => true,
        ];

        $existing = CustomerAddress::query()
            ->where('user_id', $user->id)
            ->where('cep', $cep)
            ->where('street', $payload['street'])
            ->where('number', $payload['number'])
            ->first();

        CustomerAddress::where('user_id', $user->id)->update(['is_default' => false]);

        if ($existing) {
            $existing->fill($payload);
            $existing->save();
        } else {
            CustomerAddress::create(array_merge($payload, ['user_id' => $user->id]));
        }
    }
}
