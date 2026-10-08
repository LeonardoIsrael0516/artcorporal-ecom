<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MelhorEnvioProvider implements ShippingProvider
{
    public function name(): string
    {
        return 'melhor_envio';
    }

    public function quote(array $payload): array
    {
        $cfg = $payload['settings']['melhor_envio'] ?? [];
        if (! ($cfg['enabled'] ?? false) || empty($cfg['token'])) {
            return [];
        }

        $base = ($cfg['sandbox'] ?? true)
            ? 'https://sandbox.melhorenvio.com.br'
            : 'https://melhorenvio.com.br';

        $products = [];
        foreach ($payload['items'] as $i => $item) {
            $products[] = [
                'id' => (string) ($i + 1),
                'width' => max(1, (int) ceil($item['width_cm'])),
                'height' => max(1, (int) ceil($item['height_cm'])),
                'length' => max(1, (int) ceil($item['length_cm'])),
                'weight' => max(0.01, round($item['weight_g'] / 1000, 3)),
                'insurance_value' => max(0.01, (float) $item['price']),
                'quantity' => max(1, (int) $item['quantity']),
            ];
        }

        try {
            $response = Http::withToken($cfg['token'])
                ->acceptJson()
                ->timeout(20)
                ->post($base.'/api/v2/me/shipment/calculate', [
                    'from' => ['postal_code' => preg_replace('/\D/', '', $payload['origin_cep'])],
                    'to' => ['postal_code' => preg_replace('/\D/', '', $payload['destination_cep'])],
                    'products' => $products,
                ]);

            if (! $response->successful()) {
                Log::warning('Melhor Envio quote failed', ['status' => $response->status(), 'body' => $response->body()]);

                return [];
            }

            $out = [];
            foreach ($response->json() ?? [] as $row) {
                if (! empty($row['error'])) {
                    continue;
                }
                $price = (float) ($row['custom_price'] ?? $row['price'] ?? 0);
                $days = isset($row['custom_delivery_time']) ? (int) $row['custom_delivery_time'] : (isset($row['delivery_time']) ? (int) $row['delivery_time'] : null);
                $name = trim(($row['company']['name'] ?? '').' '.($row['name'] ?? ''));
                $out[] = [
                    'id' => 'me-'.($row['id'] ?? md5($name.$price)),
                    'provider' => $this->name(),
                    'name' => $name !== '' ? $name : 'Melhor Envio',
                    'price' => $price,
                    'days' => $days,
                    'raw' => $row,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('Melhor Envio quote exception', ['message' => $e->getMessage()]);

            return [];
        }
    }
}
