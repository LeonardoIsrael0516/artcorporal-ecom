<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\CommerceCart;
use App\Models\CommerceCheckoutSession;
use App\Services\Commerce\CommerceCartService;
use Illuminate\Support\Facades\Log;

/**
 * Esvazia o carrinho da loja quando o pedido de checkout storefront é concluído
 * (webhook / CajuPay / cartão), caso ainda haja itens.
 */
class ClearStorefrontCartOnOrderCompleted
{
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;
        $meta = is_array($order->metadata) ? $order->metadata : [];
        if (empty($meta['storefront_checkout']) && empty($meta['commerce_cart_id'])) {
            return;
        }

        $cartId = ! empty($meta['commerce_cart_id']) ? (int) $meta['commerce_cart_id'] : null;
        if (! $cartId) {
            $cartId = CommerceCheckoutSession::query()
                ->where('order_id', $order->id)
                ->whereNotNull('commerce_cart_id')
                ->value('commerce_cart_id');
            $cartId = $cartId ? (int) $cartId : null;
        }
        if (! $cartId) {
            return;
        }

        try {
            $cart = CommerceCart::with('lines')->find($cartId);
            if (! $cart || $cart->lines->isEmpty()) {
                return;
            }
            app(CommerceCartService::class)->clear($cart);
            $cart->update([
                'shipping_quote' => null,
                'shipping_address' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ClearStorefrontCartOnOrderCompleted failed', [
                'order_id' => $order->id,
                'cart_id' => $cartId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
