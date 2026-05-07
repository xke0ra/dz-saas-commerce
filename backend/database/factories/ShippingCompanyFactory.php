<?php

namespace Database\Factories;

use App\Models\ShippingCompany;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShippingCompany>
 */
class ShippingCompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'code' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'contact_phone' => '0555'.fake()->numerify('######'),
            'tracking_url_template' => null,
            'supports_home_delivery' => true,
            'supports_desk_delivery' => true,
            'is_active' => true,
            'settings' => [],
        ];
    }
}
