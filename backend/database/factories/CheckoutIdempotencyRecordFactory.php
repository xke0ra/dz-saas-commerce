<?php

namespace Database\Factories;

use App\Models\CheckoutIdempotencyRecord;
use App\Models\Order;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutIdempotencyRecord>
 */
class CheckoutIdempotencyRecordFactory extends Factory
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
            'order_id' => null,
            'idempotency_key' => fake()->uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'customer_phone' => '0555'.fake()->numerify('######'),
            'response_status' => 201,
            'completed_at' => null,
            'expires_at' => now()->addDay(),
            'metadata' => [],
        ];
    }

    public function forOrder(Order $order): self
    {
        return $this->state(fn (): array => [
            'tenant_id' => $order->tenant_id,
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'completed_at' => now(),
        ]);
    }
}
