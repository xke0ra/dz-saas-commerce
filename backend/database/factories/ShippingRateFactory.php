<?php

namespace Database\Factories;

use App\Enums\DeliveryType;
use App\Models\Commune;
use App\Models\ShippingRate;
use App\Models\Tenant;
use App\Models\Wilaya;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingRate>
 */
class ShippingRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'wilaya_id' => Wilaya::query()->value('id'),
            'commune_id' => Commune::query()->value('id'),
            'delivery_type' => DeliveryType::Home,
            'price_minor' => 60000,
            'currency' => 'DZD',
            'is_active' => true,
        ];
    }
}
