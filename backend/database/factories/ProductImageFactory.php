<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
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
            'path' => 'products/'.$product->id.'/main.jpg',
            'alt' => $product->name,
            'sort_order' => 0,
            'is_primary' => true,
            'metadata' => [],
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'alt' => $product->name,
            'path' => 'products/'.$product->id.'/main.jpg',
        ]);
    }
}
