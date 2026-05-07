<?php

namespace Database\Factories;

use App\Enums\DeliveryType;
use App\Enums\ShipmentStatus;
use App\Models\Commune;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingCompany;
use App\Models\Wilaya;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
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
            'shipping_company_id' => ShippingCompany::factory()->create(['tenant_id' => $order->tenant_id])->id,
            'failed_delivery_reason_id' => null,
            'tracking_number' => 'TRK'.Str::upper(Str::random(10)),
            'status' => ShipmentStatus::Pending,
            'delivery_type' => DeliveryType::Home,
            'wilaya_id' => Wilaya::query()->value('id'),
            'commune_id' => Commune::query()->value('id'),
            'destination_address' => fake()->streetAddress(),
            'shipping_fee_minor' => 60000,
            'currency' => 'DZD',
            'shipped_at' => null,
            'delivered_at' => null,
            'failure_note' => null,
            'metadata' => [],
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'wilaya_id' => $order->wilaya_id,
            'commune_id' => $order->commune_id,
            'destination_address' => $order->shipping_address,
            'shipping_fee_minor' => $order->shipping_fee_minor,
            'currency' => $order->currency,
        ]);
    }
}
