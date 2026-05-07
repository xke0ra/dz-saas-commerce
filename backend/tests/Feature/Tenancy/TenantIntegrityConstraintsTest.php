<?php

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Commune;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShippingCompany;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Wilaya;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->wilaya = Wilaya::query()->findOrFail(16);
    $this->commune = Commune::query()
        ->where('wilaya_id', $this->wilaya->id)
        ->firstOrFail();
});

it('rejects assigning a product to a category from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $otherCategory = Category::factory()->create(['tenant_id' => $otherTenant->id]);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $otherCategory->id,
        'name' => 'Invalid product',
        'slug' => 'invalid-product',
        'status' => ProductStatus::Active,
        'price_minor' => 100000,
        'currency' => 'DZD',
        'metadata' => [],
    ]);
})->throws(QueryException::class);

it('rejects creating an order with a store from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $otherStore = Store::factory()->for($otherTenant)->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
    ]);

    Order::query()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $otherStore->id,
        'customer_id' => $customer->id,
        'order_number' => 'ORD-INVALID-STORE',
        'status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'delivery_type' => DeliveryType::Home,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'shipping_address' => 'Alger Centre',
        'subtotal_minor' => 100000,
        'shipping_fee_minor' => 50000,
        'discount_minor' => 0,
        'total_minor' => 150000,
        'currency' => 'DZD',
        'metadata' => [],
    ]);
})->throws(QueryException::class);

it('rejects creating a payment with a payment method from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $order = createIntegrityOrder($tenant, $this->wilaya->id, $this->commune->id);
    $otherPaymentMethod = PaymentMethod::factory()->create(['tenant_id' => $otherTenant->id]);

    Payment::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'payment_method_id' => $otherPaymentMethod->id,
        'status' => PaymentStatus::Pending,
        'amount_minor' => 150000,
        'currency' => 'DZD',
        'metadata' => [],
    ]);
})->throws(QueryException::class);

it('rejects creating a shipment with a shipping company from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $order = createIntegrityOrder($tenant, $this->wilaya->id, $this->commune->id);
    $otherShippingCompany = ShippingCompany::factory()->create(['tenant_id' => $otherTenant->id]);

    Shipment::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'shipping_company_id' => $otherShippingCompany->id,
        'tracking_number' => 'TRK-INVALID-COMPANY',
        'status' => 'pending',
        'delivery_type' => DeliveryType::Home,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'destination_address' => 'Alger Centre',
        'shipping_fee_minor' => 50000,
        'currency' => 'DZD',
        'metadata' => [],
    ]);
})->throws(QueryException::class);

it('rejects records whose commune does not belong to the selected wilaya', function (): void {
    $tenant = Tenant::factory()->create();
    $oranCommune = Commune::query()
        ->where('wilaya_id', 31)
        ->firstOrFail();

    Customer::query()->create([
        'tenant_id' => $tenant->id,
        'full_name' => 'Invalid Commune',
        'phone' => '0555123456',
        'wilaya_id' => 16,
        'commune_id' => $oranCommune->id,
        'address' => 'Invalid address',
        'metadata' => [],
    ]);
})->throws(QueryException::class);

function createIntegrityOrder(Tenant $tenant, int $wilayaId, int $communeId): Order
{
    $store = Store::factory()->for($tenant)->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
    ]);

    return Order::query()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'order_number' => 'ORD-INTEGRITY-'.strtoupper(str()->random(8)),
        'status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'delivery_type' => DeliveryType::Home,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
        'shipping_address' => 'Alger Centre',
        'subtotal_minor' => 100000,
        'shipping_fee_minor' => 50000,
        'discount_minor' => 0,
        'total_minor' => 150000,
        'currency' => 'DZD',
        'metadata' => [],
    ]);
}
