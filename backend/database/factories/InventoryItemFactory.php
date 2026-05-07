<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'sku' => Str::upper(Str::random(10)),
            'quantity' => 50,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 5,
            'track_quantity' => true,
            'allow_backorders' => false,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'sku' => $product->sku,
        ]);
    }
}
