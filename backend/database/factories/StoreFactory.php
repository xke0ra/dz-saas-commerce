<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();
        $slug = Str::slug($name).'-'.Str::lower(Str::random(6));

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => $slug,
            'domain' => null,
            'subdomain' => $slug,
            'status' => StoreStatus::Active,
            'locale' => 'ar',
            'currency' => 'DZD',
            'settings' => [],
        ];
    }
}
