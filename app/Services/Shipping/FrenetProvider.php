<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FrenetProvider implements ShippingProvider
{
    public function name(): string
    {
        return 'frenet';
    }

    public function quote(array $payload): array
    {
        $cfg = $payload['settings']['frenet'] ?? [];
        if (! ($cfg['enabled'] ?? false) || empty($cfg['token'])) {
            return [];
        }

        $sellerCep = preg_replace('/\D/', '', $cfg['seller_cep'] ?: $payload['origin_cep']);
        $shippingItemArray = [];
        foreach ($payload['items'] as $item) {
            $shippingItemArray[] = [
                'Weight' => max(0.01, round($item['weight_g'] / 1000, 3)),
                'Length' => max(1, (float) $item['length_cm']),
                'Height' => max(1, (float) $item['height_cm']),
                'Width' => max(1, (float) $item['width_cm']),
                'Quantity' => max(1, (int) $item['quantity']),
                'Price' => (float) $item['price'],
            ];
        }

        try {
            $response = Http::withHeaders([
                'token' => $cfg['token'],
                'Content-Type' => 'application/json',
            ])
                ->timeout(20)
                ->post('https://api.frenet.com.br/shipping/quote', [
                    'SellerCEP' => $sellerCep,
                    'RecipientCEP' => preg_replace('/\D/', '', $payload['destination_cep']),
                    'ShipmentInvoiceValue' => collect($payload['items'])->sum(fn ($i) => $i['price'] * $i['quantity']),
                    'ShippingItemArray' => $shippingItemArray,
                ]);

            if (! $response->successful()) {
                Log::warning('Frenet quote failed', ['status' => $response->status(), 'body' => $response->body()]);

                return [];
            }

            $json = $response->json();
            $services = $json['ShippingSevicesArray'] ?? $json['ShippingServicesArray'] ?? [];
            $out = [];
            foreach ($services as $row) {
                if (! empty($row['Error'])) {
                    continue;
                }
                $price = (float) ($row['ShippingPrice'] ?? 0);
                $days = isset($row['DeliveryTime']) ? (int) $row['DeliveryTime'] : null;
                $name = trim(($row['Carrier'] ?? '').' '.($row['ServiceDescription'] ?? $row['ServiceCode'] ?? ''));
                $out[] = [
                    'id' => 'frenet-'.($row['ServiceCode'] ?? md5($name.$price)),
                    'provider' => $this->name(),
                    'name' => $name !== '' ? $name : 'Frenet',
                    'price' => $price,
                    'days' => $days,
                    'raw' => $row,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('Frenet quote exception', ['message' => $e->getMessage()]);

            return [];
        }
    }
}
