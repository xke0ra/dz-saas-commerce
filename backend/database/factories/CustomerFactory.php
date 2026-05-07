<?php

namespace Database\Factories;

use App\Models\Commune;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Wilaya;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $commune = Commune::query()
            ->orderBy('wilaya_id')
            ->orderBy('id')
            ->first();

        return [
            'tenant_id' => Tenant::factory(),
            'full_name' => fake()->name(),
            'phone' => '0555'.fake()->numerify('######'),
            'email' => null,
            'wilaya_id' => $commune?->wilaya_id ?? Wilaya::query()->orderBy('id')->value('id'),
            'commune_id' => $commune?->id,
            'address' => fake()->streetAddress(),
            'metadata' => [],
        ];
    }
}
