<?php

namespace Database\Factories;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->for($tenant)->create();

        return [
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'hostname' => fake()->unique()->domainName(),
            'status' => DomainStatus::PendingVerification,
            'verification_token' => Str::random(48),
            'verified_at' => null,
            'last_checked_at' => null,
            'is_primary' => false,
            'redirect_to_primary' => true,
            'metadata' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => DomainStatus::Active,
            'verified_at' => now(),
        ]);
    }
}
