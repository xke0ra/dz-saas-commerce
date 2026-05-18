<?php

namespace Database\Factories;

use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOptionValue>
 */
class ProductOptionValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $option = ProductOption::factory()->create();

        return [
            'tenant_id' => $option->tenant_id,
            'product_option_id' => $option->id,
            'value' => 'Large',
            'position' => 0,
        ];
    }

    public function forOption(ProductOption $option): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $option->tenant_id,
            'product_option_id' => $option->id,
        ]);
    }
}
