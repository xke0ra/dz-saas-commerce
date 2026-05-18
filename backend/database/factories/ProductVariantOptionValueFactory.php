<?php

namespace Database\Factories;

use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariantOptionValue>
 */
class ProductVariantOptionValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $variant = ProductVariant::factory()->create();
        $option = ProductOption::factory()->forProduct($variant->product)->create();
        $optionValue = ProductOptionValue::factory()->forOption($option)->create();

        return [
            'tenant_id' => $variant->tenant_id,
            'product_variant_id' => $variant->id,
            'product_option_value_id' => $optionValue->id,
        ];
    }

    public function forVariantAndOptionValue(ProductVariant $variant, ProductOptionValue $optionValue): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $variant->tenant_id,
            'product_variant_id' => $variant->id,
            'product_option_value_id' => $optionValue->id,
        ]);
    }
}
