<?php

namespace Database\Factories;

use App\Enums\OrderReturnStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderReturn>
 */
class OrderReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'return_number' => null,
            'status' => OrderReturnStatus::Requested,
            'reason' => fake()->sentence(),
            'resolution_note' => null,
            'requested_at' => now(),
            'resolved_at' => null,
            'metadata' => [],
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
        ]);
    }
}
