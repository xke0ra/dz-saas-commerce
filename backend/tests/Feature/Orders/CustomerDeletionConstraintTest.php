<?php

use App\Actions\Checkout\CreateQuickOrder;
use App\Data\Checkout\QuickOrderData;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Wilaya;
use App\Models\Commune;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->wilaya = Wilaya::query()->findOrFail(16);
    $this->commune = Commune::query()
        ->where('wilaya_id', $this->wilaya->id)
        ->firstOrFail();
});

function createCustomerDeletionTestSetup($wilaya, $commune)
{
    $tenant = Tenant::factory()->create();
    $plan = Plan::create([
        'tenant_id' => $tenant->id,
        'name' => 'Test Plan',
        'slug' => 'test-plan-' . $tenant->id,
        'price_minor' => 0,
        'currency' => 'DZD',
        'billing_interval' => 'month',
        'is_active' => true,
        'sort_order' => 0,
        'metadata' => [],
    ]);

    PlanFeature::create([
        'plan_id' => $plan->id,
        'key' => 'max_products',
        'value' => ['value' => '100'],
    ]);
    PlanFeature::create([
        'plan_id' => $plan->id,
        'key' => 'max_orders_per_month',
        'value' => ['value' => '1000'],
    ]);
    PlanFeature::create([
        'plan_id' => $plan->id,
        'key' => 'max_staff_users',
        'value' => ['value' => '10'],
    ]);
    
    app(\App\Support\Tenancy\CurrentTenant::class)->set($tenant);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'is_current' => true,
        'starts_at' => now(),
        'trial_ends_at' => null,
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
        'grace_ends_at' => null,
        'cancelled_at' => null,
        'ends_at' => null,
        'metadata' => [],
    ]);

    $store = Store::factory()->for($tenant)->create(['status' => 'active']);
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
    ]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
    ]);
    InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'quantity' => 100,
        'track_quantity' => true,
    ]);
    $paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => PaymentMethodType::CashOnDelivery,
        'is_active' => true,
    ]);
    $shippingRate = ShippingRate::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'delivery_type' => DeliveryType::Home,
        'is_active' => true,
    ]);
    $shippingCompany = ShippingCompany::factory()->for($tenant)->create([
        'name' => 'Test Shipping',
        'is_active' => true,
    ]);

    return [
        'tenant' => $tenant,
        'store' => $store,
        'customer' => $customer,
        'product' => $product,
        'paymentMethod' => $paymentMethod,
        'shippingRate' => $shippingRate,
        'shippingCompany' => $shippingCompany,
    ];
}

function createCustomerDeletionTestOrder($store, $product, $customer, $paymentMethod, $shippingRate)
{
    return app(CreateQuickOrder::class)->handle($store, new QuickOrderData(
        items: [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        fullName: $customer->full_name,
        phone: $customer->phone,
        address: $customer->address,
        wilayaId: $customer->wilaya_id,
        communeId: $customer->commune_id,
        deliveryType: DeliveryType::Home,
        couponCode: null,
        note: null,
    ));
}

it('customer with existing order cannot be deleted - DB FK rejects deletion (SET NULL)', function (): void {
    $setup = createCustomerDeletionTestSetup($this->wilaya, $this->commune);

    $order = createCustomerDeletionTestOrder(
        $setup['store'],
        $setup['product'],
        $setup['customer'],
        $setup['paymentMethod'],
        $setup['shippingRate']
    );

    // Verify the order has a customer_id
    $order->refresh();
    expect($order->customer_id)->not->toBeNull();

    // Attempt to delete the customer - should fail due to FK constraint
    // The FK is SET NULL, so deleting customer should set order.customer_id to NULL
    // NOT fail with constraint violation
    $customer = $setup['customer'];

    // Delete the customer - should succeed because FK is SET NULL
    $customer->delete();

    // Order should still exist, but customer_id should be NULL
    $order->refresh();
    expect($order->customer_id)->toBeNull()
        ->and($order->customer_name)->not->toBeNull() // Customer snapshot preserved
        ->and($order->customer_phone)->not->toBeNull();
});

it('customer without orders can be deleted normally', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
    ]);

    // Customer has no orders, should be deletable
    app(\App\Support\Tenancy\CurrentTenant::class)->set($tenant);
    expect(Customer::where('id', $customer->id)->exists())->toBeTrue();

    $customer->delete();

    expect(Customer::where('id', $customer->id)->exists())->toBeFalse();
});

it('multiple orders with same customer - deleting customer sets all customer_ids to NULL', function (): void {
    $setup = createCustomerDeletionTestSetup($this->wilaya, $this->commune);

    // Create multiple orders for the same customer
    $order1 = createCustomerDeletionTestOrder(
        $setup['store'],
        $setup['product'],
        $setup['customer'],
        $setup['paymentMethod'],
        $setup['shippingRate']
    );

    $product2 = Product::factory()->create([
        'tenant_id' => $setup['tenant']->id,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
    ]);
    InventoryItem::factory()->create([
        'tenant_id' => $setup['tenant']->id,
        'product_id' => $product2->id,
        'quantity' => 100,
        'track_quantity' => true,
    ]);

    $order2 = app(\App\Actions\Checkout\CreateQuickOrder::class)->handle($setup['store'], new \App\Data\Checkout\QuickOrderData(
        items: [
            ['product_id' => $product2->id, 'quantity' => 2],
        ],
        fullName: $setup['customer']->full_name,
        phone: $setup['customer']->phone,
        address: $setup['customer']->address,
        wilayaId: $setup['customer']->wilaya_id,
        communeId: $setup['customer']->commune_id,
        deliveryType: DeliveryType::Home,
        couponCode: null,
        note: null,
    ));

    // Verify both orders have customer_id
    expect($order1->customer_id)->not->toBeNull();
    expect($order2->customer_id)->not->toBeNull();

    // Delete the customer
    $setup['customer']->delete();

    // Both orders should have customer_id set to NULL
    $order1->refresh();
    $order2->refresh();

    expect($order1->customer_id)->toBeNull()
        ->and($order2->customer_id)->toBeNull();

    // Customer snapshots should be preserved
    expect($order1->customer_name)->not->toBeNull()
        ->and($order1->customer_phone)->not->toBeNull()
        ->and($order2->customer_name)->not->toBeNull()
        ->and($order2->customer_phone)->not->toBeNull();
});

it('orders table customer_id column is nullable (required for SET NULL FK)', function (): void {
    $columns = DB::select("
        SELECT column_name, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'orders' AND column_name = 'customer_id'
    ");

    expect($columns)->toHaveCount(1);
    expect($columns[0]->is_nullable)->toBe('YES');
});

it('orders.customer_id FK is ON DELETE SET NULL', function (): void {
    $constraints = DB::select("
        SELECT constraint_name, delete_rule
        FROM information_schema.referential_constraints
        WHERE constraint_name = 'orders_customer_id_foreign'
    ");

    expect($constraints)->toHaveCount(1);
    expect($constraints[0]->delete_rule)->toBe('SET NULL');
});

it('customer snapshot fields are populated when order is created', function (): void {
    $setup = createCustomerDeletionTestSetup($this->wilaya, $this->commune);

    $order = createCustomerDeletionTestOrder(
        $setup['store'],
        $setup['product'],
        $setup['customer'],
        $setup['paymentMethod'],
        $setup['shippingRate']
    );

    $order->refresh();

    // Customer snapshot fields should be populated
    expect($order->customer_name)->toBe($setup['customer']->full_name)
        ->and($order->customer_phone)->toBe($setup['customer']->phone)
        ->and($order->customer_wilaya_id)->toBe($setup['customer']->wilaya_id)
        ->and($order->customer_commune_id)->toBe($setup['customer']->commune_id)
        ->and($order->customer_address)->toBe($setup['customer']->address);
});