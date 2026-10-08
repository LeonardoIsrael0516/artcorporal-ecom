<?php

namespace App\Services\Shipping;

use App\Models\Product;
use App\Services\Storefront\StoreThemeService;

class ShippingQuoteService
{
    public function __construct(
        protected StoreThemeService $themeService,
        protected MelhorEnvioProvider $melhorEnvio,
        protected FrenetProvider $frenet,
    ) {}

    /**
     * @param  list<array{product_id?: string, product?: Product, quantity: int}>  $lines
     * @return list<array{id: string, provider: string, name: string, price: float, days: int|null}>
     */
    public function quote(string $destinationCep, array $lines, ?int $tenantId = null): array
    {
        $settings = $this->themeService->getShippingSettings($tenantId);
        $origin = preg_replace('/\D/', '', (string) ($settings['origin_cep'] ?? ''));
        $dest = preg_replace('/\D/', '', $destinationCep);
        if (strlen($dest) !== 8) {
            return [];
        }

        $items = [];
        foreach ($lines as $line) {
            $product = $line['product'] ?? null;
            if (! $product && ! empty($line['product_id'])) {
                $product = Product::find($line['product_id']);
            }
            if (! $product instanceof Product) {
                continue;
            }
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $items[] = [
                'quantity' => $qty,
                'weight_g' => (int) ($product->weight_g ?: $settings['fallback_weight_g']),
                'height_cm' => (float) ($product->height_cm ?: $settings['fallback_height_cm']),
                'width_cm' => (float) ($product->width_cm ?: $settings['fallback_width_cm']),
                'length_cm' => (float) ($product->length_cm ?: $settings['fallback_length_cm']),
                'price' => (float) $product->price,
            ];
        }

        if ($items === []) {
            return [];
        }

        $payload = [
            'origin_cep' => $origin,
            'destination_cep' => $dest,
            'items' => $items,
            'settings' => $settings,
        ];

        $quotes = array_merge(
            $this->melhorEnvio->quote($payload),
            $this->frenet->quote($payload),
        );

        $markup = (float) ($settings['markup_percent'] ?? 0);
        $extraDays = (int) ($settings['extra_days'] ?? 0);

        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item['price'] * (int) $item['quantity'];
        }

        $freeCfg = is_array($settings['free_shipping'] ?? null) ? $settings['free_shipping'] : [];
        $freeEnabled = ! empty($freeCfg['enabled']);
        $freeMin = max(0, (float) ($freeCfg['min_amount'] ?? 0));
        $freeApplies = $freeEnabled && $freeMin > 0 && $subtotal >= $freeMin;

        $normalized = [];
        foreach ($quotes as $q) {
            $price = (float) $q['price'];
            if ($markup > 0) {
                $price = round($price * (1 + $markup / 100), 2);
            }
            if ($freeApplies) {
                $price = 0.0;
            }
            $days = $q['days'] !== null ? ((int) $q['days'] + $extraDays) : null;
            $name = (string) $q['name'];
            if ($freeApplies && $price <= 0 && stripos($name, 'grátis') === false && stripos($name, 'gratis') === false) {
                $name = $name.' (Frete grátis)';
            }
            $key = strtolower(trim($name)).'|'.$price.'|'.$days;
            if (isset($normalized[$key])) {
                continue;
            }
            $normalized[$key] = [
                'id' => $q['id'],
                'provider' => $q['provider'],
                'name' => $name,
                'price' => $price,
                'days' => $days,
                'free_shipping' => $freeApplies,
            ];
        }

        $list = array_values($normalized);
        usort($list, fn ($a, $b) => $a['price'] <=> $b['price']);

        return $list;
    }
}
