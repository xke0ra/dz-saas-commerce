<?php

namespace Database\Factories;

use App\Models\FailedDeliveryReason;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FailedDeliveryReason>
 */
class FailedDeliveryReasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->words(3, true);

        return [
            'tenant_id' => Tenant::factory(),
            'code' => Str::slug($label).'-'.Str::lower(Str::random(6)),
            'label_ar' => 'تعذر التسليم',
            'label_fr' => Str::title($label),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
