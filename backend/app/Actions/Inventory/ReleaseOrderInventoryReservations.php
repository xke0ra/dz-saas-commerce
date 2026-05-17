<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
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

                $previousReserved = $inventoryItem->reserved_quantity;
                $newReserved = max(0, $previousReserved - $item->quantity);
                $releasedQuantity = $previousReserved - $newReserved;

                $inventoryItem->update([
                    'reserved_quantity' => $newReserved,
                ]);

                if ($releasedQuantity > 0) {
                    $this->recordStockRelease($inventoryItem, $lockedOrder, $item, $releasedQuantity);
                }
            }

            $lockedOrder->update([
                'metadata' => array_merge($metadata, [
                    'inventory_released_at' => now()->toISOString(),
                ]),
            ]);

            return $lockedOrder->refresh();
        });
    }

    private function recordStockRelease(
        InventoryItem $inventoryItem,
        Order $order,
        OrderItem $orderItem,
        int $releasedQuantity,
    ): void {
        StockMovement::query()
            ->withoutGlobalScope('current_tenant')
            ->create([
                'tenant_id' => $order->tenant_id,
                'product_id' => $orderItem->product_id,
                'inventory_item_id' => $inventoryItem->id,
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'order_return_id' => null,
                'actor_id' => null,
                'type' => StockMovementType::Released,
                'quantity_delta' => 0,
                'reserved_delta' => -$releasedQuantity,
                'balance_quantity_after' => $inventoryItem->quantity,
                'balance_reserved_after' => $inventoryItem->reserved_quantity,
                'reason' => 'order_inventory_release',
                'metadata' => [
                    'source' => 'release_order_inventory_reservations',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'product_id' => $orderItem->product_id,
                    'order_item_id' => $orderItem->id,
                ],
            ]);
    }
}
