<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class RestockOrderReturn
{
    public function handle(OrderReturn $orderReturn): OrderReturn
    {
        return DB::transaction(function () use ($orderReturn): OrderReturn {
            $lockedReturn = OrderReturn::query()
                ->whereKey($orderReturn->getKey())
                ->with('order.items')
                ->lockForUpdate()
                ->firstOrFail();

            $metadata = $lockedReturn->metadata ?? [];

            if (isset($metadata['restocked_at'])) {
                return $lockedReturn;
            }

            foreach ($lockedReturn->order->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                $inventoryItem = InventoryItem::query()
                    ->withoutGlobalScope('current_tenant')
                    ->where('tenant_id', $lockedReturn->tenant_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventoryItem === null || ! $inventoryItem->track_quantity) {
                    continue;
                }

                $previousQuantity = $inventoryItem->quantity;

                $inventoryItem->increment('quantity', $item->quantity);
                $inventoryItem->refresh();

                $restockedQuantity = $inventoryItem->quantity - $previousQuantity;

                if ($restockedQuantity > 0) {
                    $this->recordStockRestock($inventoryItem, $lockedReturn, $item, $restockedQuantity);
                }
            }

            $lockedReturn->update([
                'metadata' => array_merge($metadata, [
                    'restocked_at' => now()->toISOString(),
                ]),
            ]);

            return $lockedReturn->refresh()->load('order.items');
        });
    }

    private function recordStockRestock(
        InventoryItem $inventoryItem,
        OrderReturn $orderReturn,
        OrderItem $orderItem,
        int $restockedQuantity,
    ): void {
        StockMovement::query()
            ->withoutGlobalScope('current_tenant')
            ->create([
                'tenant_id' => $orderReturn->tenant_id,
                'product_id' => $orderItem->product_id,
                'inventory_item_id' => $inventoryItem->id,
                'order_id' => $orderReturn->order_id,
                'order_item_id' => $orderItem->id,
                'order_return_id' => $orderReturn->id,
                'actor_id' => null,
                'type' => StockMovementType::Restocked,
                'quantity_delta' => $restockedQuantity,
                'reserved_delta' => 0,
                'balance_quantity_after' => $inventoryItem->quantity,
                'balance_reserved_after' => $inventoryItem->reserved_quantity,
                'reason' => 'order_return_restock',
                'metadata' => [
                    'source' => 'restock_order_return',
                    'order_id' => $orderReturn->order_id,
                    'order_number' => $orderReturn->order?->order_number,
                    'order_return_id' => $orderReturn->id,
                    'product_id' => $orderItem->product_id,
                    'order_item_id' => $orderItem->id,
                ],
            ]);
    }
}
