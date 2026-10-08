<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\OrderItem;
use App\Models\Product;

class DecrementStockOnOrderCompleted
{
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;
        $items = OrderItem::query()->where('order_id', $order->id)->get();
        if ($items->isEmpty() && $order->product_id) {
            $product = Product::find($order->product_id);
            if ($product && $product->isPhysical()) {
                $product->decrementStock(1);
            }

            return;
        }

        foreach ($items as $item) {
            $product = Product::find($item->product_id);
            if (! $product || ! $product->isPhysical()) {
                continue;
            }
            $qty = 1;
            if (is_array($order->metadata['commerce_multi_line'] ?? null) || ! empty($order->metadata['commerce_multi_line'])) {
                // quantity stored in line snapshots if available
                $snapshots = $order->metadata['line_items'] ?? [];
                foreach ($snapshots as $snap) {
                    if (($snap['product_id'] ?? null) === $product->id) {
                        $qty = max(1, (int) ($snap['quantity'] ?? 1));
                        break;
                    }
                }
            }
            $product->decrementStock($qty);
        }
    }
}
