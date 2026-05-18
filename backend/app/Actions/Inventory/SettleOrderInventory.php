<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class SettleOrderInventory
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

            if (isset($metadata['inventory_settled_at'])) {
                return $lockedOrder;
            }

            foreach ($lockedOrder->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                $inventoryItem = $this->inventoryItemForOrderItem($lockedOrder->tenant_id, $item);

                if ($inventoryItem === null || ! $inventoryItem->track_quantity) {
                    continue;
                }

                $previousQuantity = $inventoryItem->quantity;
                $previousReserved = $inventoryItem->reserved_quantity;
                $newQuantity = max(0, $previousQuantity - $item->quantity);
                $newReserved = max(0, $previousReserved - $item->quantity);
                $settledQuantity = $previousQuantity - $newQuantity;
                $settledReserved = $previousReserved - $newReserved;

                $inventoryItem->update([
                    'quantity' => $newQuantity,
                    'reserved_quantity' => $newReserved,
                ]);

                if ($settledQuantity > 0 || $settledReserved > 0) {
                    $this->recordStockSettlement($inventoryItem, $lockedOrder, $item, $settledQuantity, $settledReserved);
                }
            }

            $lockedOrder->update([
                'metadata' => array_merge($metadata, [
                    'inventory_settled_at' => now()->toISOString(),
                ]),
            ]);

            return $lockedOrder->refresh();
        });
    }

    private function recordStockSettlement(
        InventoryItem $inventoryItem,
        Order $order,
        OrderItem $orderItem,
        int $settledQuantity,
        int $settledReserved,
    ): void {
        StockMovement::query()
            ->withoutGlobalScope('current_tenant')
            ->create([
                'tenant_id' => $order->tenant_id,
                'product_id' => $orderItem->product_id,
                'product_variant_id' => $orderItem->product_variant_id,
                'inventory_item_id' => $inventoryItem->id,
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'order_return_id' => null,
                'actor_id' => null,
                'type' => StockMovementType::Settled,
                'quantity_delta' => -$settledQuantity,
                'reserved_delta' => -$settledReserved,
                'balance_quantity_after' => $inventoryItem->quantity,
                'balance_reserved_after' => $inventoryItem->reserved_quantity,
                'reason' => 'order_inventory_settlement',
                'metadata' => [
                    'source' => 'settle_order_inventory',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'product_id' => $orderItem->product_id,
                    'order_item_id' => $orderItem->id,
                ],
            ]);
    }

    private function inventoryItemForOrderItem(string $tenantId, OrderItem $orderItem): ?InventoryItem
    {
        return InventoryItem::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $orderItem->product_id)
            ->when(
                $orderItem->product_variant_id === null,
                fn ($query) => $query->whereNull('product_variant_id'),
                fn ($query) => $query->where('product_variant_id', $orderItem->product_variant_id),
            )
            ->lockForUpdate()
            ->first();
    }
}
