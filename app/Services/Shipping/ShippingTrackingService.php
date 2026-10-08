<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Services\Storefront\StoreThemeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingTrackingService
{
    public function __construct(
        protected StoreThemeService $themeService,
    ) {}

    /**
     * Consulta rastreio na Frenet e devolve eventos normalizados.
     *
     * @return array{
     *   ok: bool,
     *   tracking_number: string,
     *   tracking_url: string|null,
     *   carrier_code: string|null,
     *   service_description: string|null,
     *   events: list<array{at: string|null, description: string, location: string|null, type: string|null}>,
     *   message?: string
     * }
     */
    public function trackWithFrenet(int $tenantId, string $trackingNumber, ?string $serviceCode = null, ?string $carrierCode = null): array
    {
        $trackingNumber = trim($trackingNumber);
        $settings = $this->themeService->getShippingSettings($tenantId);
        $frenet = $settings['frenet'] ?? [];
        $token = trim((string) ($frenet['token'] ?? ''));

        if ($token === '' || empty($frenet['enabled'])) {
            return [
                'ok' => false,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $this->publicTrackingUrl($carrierCode, $trackingNumber),
                'carrier_code' => $carrierCode,
                'service_description' => null,
                'events' => [],
                'message' => 'Frenet não configurada.',
            ];
        }

        if ($trackingNumber === '') {
            return [
                'ok' => false,
                'tracking_number' => '',
                'tracking_url' => null,
                'carrier_code' => $carrierCode,
                'service_description' => null,
                'events' => [],
                'message' => 'Informe o código de rastreio.',
            ];
        }

        $body = [
            'TrackingNumber' => $trackingNumber,
        ];
        if ($serviceCode) {
            $body['ShippingServiceCode'] = $serviceCode;
        }

        try {
            $response = Http::withHeaders([
                'token' => $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(20)
                ->post('https://api.frenet.com.br/tracking/trackinginfo', $body);

            if (! $response->successful()) {
                Log::warning('Frenet tracking failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'ok' => false,
                    'tracking_number' => $trackingNumber,
                    'tracking_url' => $this->publicTrackingUrl($carrierCode, $trackingNumber),
                    'carrier_code' => $carrierCode,
                    'service_description' => null,
                    'events' => [],
                    'message' => 'Não foi possível consultar o rastreio na Frenet.',
                ];
            }

            $json = $response->json();
            if (! is_array($json)) {
                $json = [];
            }

            $resolvedCarrier = $carrierCode
                ?: (isset($json['CarrierCode']) ? (string) $json['CarrierCode'] : null);
            $trackingUrl = isset($json['TrackingUrl']) && is_string($json['TrackingUrl']) && $json['TrackingUrl'] !== ''
                ? $json['TrackingUrl']
                : $this->publicTrackingUrl($resolvedCarrier, $trackingNumber);

            $events = [];
            $rawEvents = $json['TrackingEvents'] ?? [];
            if (is_array($rawEvents)) {
                foreach ($rawEvents as $ev) {
                    if (! is_array($ev)) {
                        continue;
                    }
                    $events[] = [
                        'at' => isset($ev['EventDateTime']) ? (string) $ev['EventDateTime'] : null,
                        'description' => (string) ($ev['EventDescription'] ?? $ev['Description'] ?? ''),
                        'location' => isset($ev['EventLocation']) ? (string) $ev['EventLocation'] : null,
                        'type' => isset($ev['EventType']) ? (string) $ev['EventType'] : null,
                    ];
                }
            }

            return [
                'ok' => true,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'carrier_code' => $resolvedCarrier,
                'service_description' => isset($json['ServiceDescrition'])
                    ? (string) $json['ServiceDescrition']
                    : (isset($json['ServiceDescription']) ? (string) $json['ServiceDescription'] : null),
                'events' => $events,
            ];
        } catch (\Throwable $e) {
            Log::warning('Frenet tracking exception', ['message' => $e->getMessage()]);

            return [
                'ok' => false,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $this->publicTrackingUrl($carrierCode, $trackingNumber),
                'carrier_code' => $carrierCode,
                'service_description' => null,
                'events' => [],
                'message' => $e->getMessage() ?: 'Falha ao consultar rastreio.',
            ];
        }
    }

    /**
     * Atualiza pedido com código de rastreio e, se possível, eventos da Frenet.
     *
     * @param  array{tracking?: string|null, fulfillment_status?: string|null, sync_frenet?: bool}  $input
     * @return array{order: Order, tracking: array<string, mixed>}
     */
    public function updateOrderTracking(Order $order, array $input): array
    {
        $tracking = trim((string) ($input['tracking'] ?? $order->shipping_tracking ?? ''));
        $fulfillment = $input['fulfillment_status'] ?? null;
        $syncFrenet = (bool) ($input['sync_frenet'] ?? true);

        $meta = is_array($order->metadata) ? $order->metadata : [];
        $quote = is_array($meta['shipping_quote'] ?? null) ? $meta['shipping_quote'] : [];
        $raw = is_array($quote['raw'] ?? null) ? $quote['raw'] : [];
        $serviceCode = $raw['ServiceCode'] ?? ($quote['service_code'] ?? null);
        $carrierCode = $raw['CarrierCode'] ?? ($meta['shipping_carrier_code'] ?? null);

        $trackingPayload = [
            'ok' => false,
            'tracking_number' => $tracking,
            'tracking_url' => $this->publicTrackingUrl(is_string($carrierCode) ? $carrierCode : null, $tracking),
            'carrier_code' => is_string($carrierCode) ? $carrierCode : null,
            'service_description' => null,
            'events' => [],
        ];

        if ($syncFrenet && $tracking !== '' && ($order->shipping_provider === 'frenet' || ! empty($serviceCode))) {
            $trackingPayload = $this->trackWithFrenet(
                (int) $order->tenant_id,
                $tracking,
                is_string($serviceCode) ? $serviceCode : null,
                is_string($carrierCode) ? $carrierCode : null
            );
        } elseif ($tracking !== '') {
            $trackingPayload['ok'] = true;
        }

        if ($fulfillment === null || $fulfillment === '') {
            $fulfillment = $tracking !== ''
                ? 'shipped'
                : ($meta['fulfillment_status'] ?? 'preparing');
        }
        $allowed = ['preparing', 'shipped', 'delivered', 'returned'];
        if (! in_array($fulfillment, $allowed, true)) {
            $fulfillment = 'preparing';
        }

        if (! empty($trackingPayload['events'])) {
            $lastType = (string) ($trackingPayload['events'][0]['type'] ?? '');
            if ($lastType === '9') {
                $fulfillment = 'delivered';
            }
        }

        $meta['fulfillment_status'] = $fulfillment;
        $meta['shipping_tracking_url'] = $trackingPayload['tracking_url'] ?? null;
        $meta['shipping_carrier_code'] = $trackingPayload['carrier_code'] ?? $carrierCode;
        $meta['shipping_tracking_events'] = $trackingPayload['events'] ?? [];
        $meta['shipping_tracking_synced_at'] = now()->toIso8601String();

        $order->shipping_tracking = $tracking !== '' ? $tracking : null;
        $order->metadata = $meta;
        $order->save();

        return [
            'order' => $order->fresh(['product', 'user', 'orderItems']),
            'tracking' => $trackingPayload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fulfillmentPayload(Order $order): array
    {
        $meta = is_array($order->metadata) ? $order->metadata : [];
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];
        $events = is_array($meta['shipping_tracking_events'] ?? null) ? $meta['shipping_tracking_events'] : [];
        $status = (string) ($meta['fulfillment_status'] ?? ($order->shipping_tracking ? 'shipped' : 'preparing'));

        $trackingUrl = $meta['shipping_tracking_url'] ?? null;
        if (! $trackingUrl && $order->shipping_tracking) {
            $trackingUrl = $this->publicTrackingUrl(
                isset($meta['shipping_carrier_code']) ? (string) $meta['shipping_carrier_code'] : null,
                (string) $order->shipping_tracking
            );
        }

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'shipped' => 'Enviado',
                'delivered' => 'Entregue',
                'returned' => 'Devolvido',
                default => 'Preparando envio',
            },
            'service' => $order->shipping_service,
            'provider' => $order->shipping_provider,
            'days' => $order->shipping_days,
            'amount' => $order->shipping_amount !== null ? (float) $order->shipping_amount : null,
            'tracking' => $order->shipping_tracking,
            'tracking_url' => $trackingUrl,
            'events' => $events,
            'address' => $address,
            'address_text' => $this->formatAddress($address),
        ];
    }

    public function publicTrackingUrl(?string $carrierCode, string $trackingNumber): ?string
    {
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            return null;
        }
        $carrier = strtoupper(trim((string) $carrierCode));
        if ($carrier !== '') {
            return 'https://rastreio.frenet.com.br/'.$carrier.'/'.$trackingNumber;
        }

        return 'https://rastreamento.correios.com.br/app/index.php?objetos='.urlencode($trackingNumber);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    public function formatAddress(array $address): string
    {
        if ($address === []) {
            return '';
        }

        $parts = [
            $address['street'] ?? null,
            $address['number'] ?? null,
            $address['complement'] ?? null,
            $address['district'] ?? null,
            isset($address['city'], $address['state'])
                ? $address['city'].' - '.$address['state']
                : ($address['city'] ?? $address['state'] ?? null),
            ! empty($address['cep'])
                ? 'CEP '.preg_replace('/(\d{5})(\d{3})/', '$1-$2', preg_replace('/\D/', '', (string) $address['cep']))
                : null,
        ];

        return implode(', ', array_filter($parts));
    }

    public function isStorefrontOrder(Order $order): bool
    {
        $meta = is_array($order->metadata) ? $order->metadata : [];
        if (! empty($meta['storefront_checkout']) || ! empty($meta['commerce_multi_line'])) {
            return true;
        }
        if ($order->product && method_exists($order->product, 'isPhysical') && $order->product->isPhysical()) {
            return true;
        }

        return is_array($order->shipping_address) && $order->shipping_address !== [];
    }
}
