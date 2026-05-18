<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
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
            'sku' => null,
            'option_signature' => 'size=large',
            'title' => null,
            'price_minor' => null,
            'compare_at_price_minor' => null,
            'cost_price_minor' => null,
            'status' => ProductStatus::Active,
            'sort_order' => 0,
            'metadata' => [],
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
        ]);
    }

    public function withSku(?string $sku): static
    {
        return $this->state(fn (array $attributes): array => [
            'sku' => $sku,
        ]);
    }

    public function withPriceOverride(int $priceMinor): static
    {
        return $this->state(fn (array $attributes): array => [
            'price_minor' => $priceMinor,
        ]);
    }
}
