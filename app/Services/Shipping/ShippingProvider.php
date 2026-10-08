<?php

namespace App\Services\Shipping;

interface ShippingProvider
{
    public function name(): string;

    /**
     * @param  array{
     *   origin_cep: string,
     *   destination_cep: string,
     *   items: list<array{quantity:int, weight_g:int, height_cm:float, width_cm:float, length_cm:float, price:float}>,
     *   settings: array<string, mixed>
     * }  $payload
     * @return list<array{id: string, provider: string, name: string, price: float, days: int|null, raw?: mixed}>
     */
    public function quote(array $payload): array;
}
