<?php

namespace App\Services\Commerce;

use App\Events\Commerce\CartLineAdded;
use App\Events\Commerce\CartUpdated;
use App\Models\CommerceCart;
use App\Models\CommerceCartLine;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SubscriptionPlan;
use App\Plugins\Commerce\PluginCommercePricing;
use App\Plugins\Commerce\PluginTenantGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommerceCartService
{
    public const COOKIE_NAME = 'getfy_commerce_cart';

    public function getOrCreate(Request $request, int $tenantId): CommerceCart
    {
        PluginTenantGuard::assertTenantId($tenantId);
        $token = $request->cookie(self::COOKIE_NAME);
        if (is_string($token) && $token !== '') {
            $cart = CommerceCart::where('session_token', $token)
                ->where('tenant_id', $tenantId)
                ->with('lines')
                ->first();
            if ($cart && ! $cart->isExpired()) {
                return $cart;
            }
        }

        $ttlDays = (int) config('plugins.commerce_cart_ttl_days', 14);
        $cart = CommerceCart::create([
            'tenant_id' => $tenantId,
            'session_token' => Str::random(48),
            'expires_at' => now()->addDays(max(1, $ttlDays)),
        ]);

        return $cart->load('lines');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addLine(CommerceCart $cart, array $payload): CommerceCartLine
    {
        $this->assertCartActive($cart);
        $maxLines = (int) config('plugins.commerce_cart_max_lines', 50);
        if ($cart->lines()->count() >= $maxLines) {
            abort(422, 'Carrinho cheio.');
        }

        $product = Product::forTenant($cart->tenant_id)
            ->where('id', $payload['product_id'] ?? '')
            ->where('is_active', true)
            ->firstOrFail();

        $offer = null;
        if (! empty($payload['product_offer_id'])) {
            $offer = ProductOffer::where('id', (int) $payload['product_offer_id'])
                ->where('product_id', $product->id)
                ->firstOrFail();
        }

        $plan = null;
        if (! empty($payload['subscription_plan_id'])) {
            $plan = SubscriptionPlan::where('id', (int) $payload['subscription_plan_id'])
                ->where('product_id', $product->id)
                ->firstOrFail();
        }

        $qty = $this->clampQuantity($product, (int) ($payload['quantity'] ?? 1));
        $this->assertPurchaseAllowed($product, $qty);

        $unit = PluginCommercePricing::unitAmountBrl($product, $offer, $plan, $qty);
        $meta = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $meta = array_merge($meta, PluginCommercePricing::wholesaleLineMeta($product, $qty, $unit));

        // Se já existe linha do mesmo produto (sem oferta/plano), soma quantidade e recalcula.
        if ($offer === null && $plan === null) {
            $existing = $cart->lines()
                ->where('product_id', $product->id)
                ->whereNull('product_offer_id')
                ->whereNull('subscription_plan_id')
                ->first();

            if ($existing) {
                $newQty = $this->clampQuantity($product, (int) $existing->quantity + $qty);
                $this->assertPurchaseAllowed($product, $newQty);
                $unit = PluginCommercePricing::unitAmountBrl($product, null, null, $newQty);
                $existing->update([
                    'quantity' => $newQty,
                    'unit_amount' => $unit,
                    'metadata' => array_merge(
                        is_array($existing->metadata) ? $existing->metadata : [],
                        PluginCommercePricing::wholesaleLineMeta($product, $newQty, $unit)
                    ),
                ]);
                event(new CartUpdated($cart->fresh('lines')));

                return $existing->fresh();
            }
        }

        $line = CommerceCartLine::create([
            'commerce_cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_offer_id' => $offer?->id,
            'subscription_plan_id' => $plan?->id,
            'quantity' => $qty,
            'unit_amount' => $unit,
            'metadata' => $meta,
            'position' => (int) $cart->lines()->max('position') + 1,
        ]);

        event(new CartLineAdded($cart, $line));
        event(new CartUpdated($cart->fresh('lines')));

        return $line;
    }

    public function updateQuantity(CommerceCart $cart, int $lineId, int $quantity): CommerceCartLine
    {
        $this->assertCartActive($cart);
        $line = $cart->lines()->where('id', $lineId)->firstOrFail();
        $product = Product::forTenant($cart->tenant_id)->where('id', $line->product_id)->firstOrFail();

        $qty = $this->clampQuantity($product, $quantity);
        $this->assertPurchaseAllowed($product, $qty);

        $offer = $line->product_offer_id
            ? ProductOffer::where('id', $line->product_offer_id)->where('product_id', $product->id)->first()
            : null;
        $plan = $line->subscription_plan_id
            ? SubscriptionPlan::where('id', $line->subscription_plan_id)->where('product_id', $product->id)->first()
            : null;

        $unit = PluginCommercePricing::unitAmountBrl($product, $offer, $plan, $qty);
        $meta = array_merge(
            is_array($line->metadata) ? $line->metadata : [],
            PluginCommercePricing::wholesaleLineMeta($product, $qty, $unit)
        );

        $line->update([
            'quantity' => $qty,
            'unit_amount' => $unit,
            'metadata' => $meta,
        ]);
        event(new CartUpdated($cart->fresh('lines')));

        return $line->fresh();
    }

    /**
     * Recalcula unit_amount de todas as linhas (ex.: antes do checkout).
     */
    public function repriceLines(CommerceCart $cart): void
    {
        $cart->loadMissing('lines');
        foreach ($cart->lines as $line) {
            $product = Product::forTenant($cart->tenant_id)
                ->where('id', $line->product_id)
                ->where('is_active', true)
                ->first();
            if (! $product) {
                continue;
            }

            $qty = $this->clampQuantity($product, (int) $line->quantity);
            $this->assertPurchaseAllowed($product, $qty);

            $offer = $line->product_offer_id
                ? ProductOffer::where('id', $line->product_offer_id)->where('product_id', $product->id)->first()
                : null;
            $plan = $line->subscription_plan_id
                ? SubscriptionPlan::where('id', $line->subscription_plan_id)->where('product_id', $product->id)->first()
                : null;

            $unit = PluginCommercePricing::unitAmountBrl($product, $offer, $plan, $qty);
            $line->update([
                'quantity' => $qty,
                'unit_amount' => $unit,
                'metadata' => array_merge(
                    is_array($line->metadata) ? $line->metadata : [],
                    PluginCommercePricing::wholesaleLineMeta($product, $qty, $unit)
                ),
            ]);
        }
        $cart->unsetRelation('lines');
        $cart->load('lines');
    }

    public function removeLine(CommerceCart $cart, int $lineId): void
    {
        $this->assertCartActive($cart);
        $cart->lines()->where('id', $lineId)->delete();
        event(new CartUpdated($cart->fresh('lines')));
    }

    public function clear(CommerceCart $cart): void
    {
        $cart->lines()->delete();
        event(new CartUpdated($cart->fresh('lines')));
    }

    /**
     * @return array{subtotal: float, currency: string, line_count: int}
     */
    public function totals(CommerceCart $cart): array
    {
        $cart->loadMissing('lines.product');
        $currency = 'BRL';
        $subtotal = 0.0;
        foreach ($cart->lines as $line) {
            $product = $line->product;
            if ($product) {
                $offer = $line->product_offer_id
                    ? ProductOffer::find($line->product_offer_id)
                    : null;
                $plan = $line->subscription_plan_id
                    ? SubscriptionPlan::find($line->subscription_plan_id)
                    : null;
                $currency = PluginCommercePricing::currency($product, $offer, $plan);
            }
            $subtotal += (float) $line->unit_amount * (int) $line->quantity;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'currency' => $currency,
            'line_count' => $cart->lines->count(),
        ];
    }

    public function cartCookie(CommerceCart $cart): \Symfony\Component\HttpFoundation\Cookie
    {
        $ttlDays = (int) config('plugins.commerce_cart_ttl_days', 14);

        return cookie(
            self::COOKIE_NAME,
            $cart->session_token,
            max(1, $ttlDays) * 24 * 60,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            'lax'
        );
    }

    private function clampQuantity(Product $product, int $quantity): int
    {
        $min = $product->minPurchaseQty();
        $max = $product->maxPurchaseQty();

        return max($min, min($max, $quantity));
    }

    private function assertPurchaseAllowed(Product $product, int $qty): void
    {
        if ($product->hasWholesale() && (bool) $product->wholesale_only && $qty < $product->wholesaleMinQty()) {
            abort(422, 'Quantidade mínima para atacado: '.$product->wholesaleMinQty().' unidades.');
        }

        if (! $product->inStock($qty)) {
            abort(422, 'Estoque insuficiente.');
        }
    }

    private function assertCartActive(CommerceCart $cart): void
    {
        if ($cart->isExpired()) {
            abort(410, 'Carrinho expirado.');
        }
    }
}
