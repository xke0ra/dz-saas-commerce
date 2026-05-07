<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\OrderReturn;
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

                $inventoryItem->increment('quantity', $item->quantity);
            }

            $lockedReturn->update([
                'metadata' => array_merge($metadata, [
                    'restocked_at' => now()->toISOString(),
                ]),
            ]);

            return $lockedReturn->refresh()->load('order.items');
        });
    }
}
