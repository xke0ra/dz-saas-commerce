<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inventoryItem = InventoryItem::factory()->create();

        return [
            'tenant_id' => $inventoryItem->tenant_id,
            'product_id' => $inventoryItem->product_id,
            'inventory_item_id' => $inventoryItem->id,
            'order_id' => null,
            'order_item_id' => null,
            'order_return_id' => null,
            'actor_id' => null,
            'type' => StockMovementType::ManualAdjustment,
            'quantity_delta' => 1,
            'reserved_delta' => 0,
            'balance_quantity_after' => $inventoryItem->quantity + 1,
            'balance_reserved_after' => $inventoryItem->reserved_quantity,
            'reason' => fake()->sentence(),
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }

    public function forInventoryItem(InventoryItem $inventoryItem): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $inventoryItem->tenant_id,
            'product_id' => $inventoryItem->product_id,
            'inventory_item_id' => $inventoryItem->id,
        ]);
    }
}
