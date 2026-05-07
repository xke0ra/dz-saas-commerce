<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ReleaseOrderInventoryReservations
{
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            $metadata = $lockedOrder->metadata ?? [];

            if (isset($metadata['inventory_settled_at'], $metadata['inventory_released_at'])) {
                return $lockedOrder;
            }

            if (isset($metadata['inventory_settled_at'])) {
                return $lockedOrder;
            }

            if (isset($metadata['inventory_released_at'])) {
                return $lockedOrder;
            }

            foreach ($lockedOrder->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                $inventoryItem = InventoryItem::query()
                    ->withoutGlobalScope('current_tenant')
                    ->where('tenant_id', $lockedOrder->tenant_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventoryItem === null || ! $inventoryItem->track_quantity) {
                    continue;
                }

                $inventoryItem->update([
                    'reserved_quantity' => max(0, $inventoryItem->reserved_quantity - $item->quantity),
                ]);
            }

            $lockedOrder->update([
                'metadata' => array_merge($metadata, [
                    'inventory_released_at' => now()->toISOString(),
                ]),
            ]);

            return $lockedOrder->refresh();
        });
    }
}
