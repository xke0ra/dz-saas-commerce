<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'sku' => Str::upper(Str::random(10)),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => ProductStatus::Active,
            'price_minor' => fake()->numberBetween(50000, 2500000),
            'compare_at_price_minor' => null,
            'cost_price_minor' => null,
            'currency' => 'DZD',
            'requires_shipping' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'published_at' => now(),
            'metadata' => [],
        ];
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $category->tenant_id,
            'category_id' => $category->id,
        ]);
    }
}
