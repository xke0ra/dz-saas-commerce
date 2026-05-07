<?php

namespace Database\Factories;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Commune;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Wilaya;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
        $commune = Commune::query()
            ->orderBy('wilaya_id')
            ->orderBy('id')
            ->first();
        $wilayaId = $commune?->wilaya_id ?? Wilaya::query()->orderBy('id')->value('id');
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'wilaya_id' => $wilayaId,
            'commune_id' => $commune?->id,
        ]);
        $subtotal = 200000;
        $shipping = 50000;

        return [
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'delivery_type' => DeliveryType::Home,
            'wilaya_id' => $wilayaId,
            'commune_id' => $commune?->id,
            'shipping_address' => fake()->streetAddress(),
            'customer_note' => null,
            'subtotal_minor' => $subtotal,
            'shipping_fee_minor' => $shipping,
            'discount_minor' => 0,
            'total_minor' => $subtotal + $shipping,
            'currency' => 'DZD',
            'confirmed_at' => null,
            'metadata' => [],
        ];
    }
}
