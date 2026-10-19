<?php

namespace App\Plugins\Commerce;

use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SubscriptionPlan;

class PluginCommercePricing
{
    /**
     * Preço unitário efetivo em BRL para catálogo/carrinho (sem cupom).
     * Com atacado habilitado, aplica faixa conforme $quantity.
     */
    public static function unitAmountBrl(
        Product $product,
        ?ProductOffer $offer = null,
        ?SubscriptionPlan $plan = null,
        int $quantity = 1,
    ): float {
        if ($plan !== null) {
            return round((float) ($plan->price ?? $product->price ?? 0), 2);
        }
        if ($offer !== null) {
            return round((float) ($offer->price ?? $product->price ?? 0), 2);
        }

        $qty = max(1, $quantity);

        return $product->wholesaleUnitPrice($qty);
    }

    public static function currency(
        Product $product,
        ?ProductOffer $offer = null,
        ?SubscriptionPlan $plan = null,
    ): string {
        if ($plan !== null) {
            return strtoupper((string) ($plan->getCurrencyOrDefault() ?? 'BRL'));
        }
        if ($offer !== null) {
            return strtoupper((string) ($offer->getCurrencyOrDefault() ?? 'BRL'));
        }

        return strtoupper((string) ($product->currency ?? 'BRL'));
    }

    /**
     * Metadata de linha para UI/auditoria de atacado.
     *
     * @return array{retail_unit_amount: float, wholesale_applied: bool, wholesale_tier_min_qty: int|null, wholesale_savings: float}
     */
    public static function wholesaleLineMeta(Product $product, int $quantity, float $unitAmount): array
    {
        $retail = round((float) ($product->price ?? 0), 2);
        $tier = $product->hasWholesale() ? $product->wholesaleTierForQty($quantity) : null;
        $applied = $tier !== null;
        $savings = $applied ? round(max(0, ($retail - $unitAmount) * $quantity), 2) : 0.0;

        return [
            'retail_unit_amount' => $retail,
            'wholesale_applied' => $applied,
            'wholesale_tier_min_qty' => $applied ? (int) $tier['min_qty'] : null,
            'wholesale_savings' => $savings,
        ];
    }
}
