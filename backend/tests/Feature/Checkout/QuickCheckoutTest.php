<?php

use App\Actions\Billing\StartTenantSubscription;
use App\Enums\CouponType;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlanFeatureKey;
use App\Enums\ProductStatus;
use App\Enums\TenantRole;
use App\Models\CheckoutIdempotencyRecord;
use App\Models\Commune;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wilaya;
use Database\Seeders\AlgeriaGeographySeeder;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->wilaya = Wilaya::query()->findOrFail(16);
    $this->commune = Commune::query()
        ->where('wilaya_id', $this->wilaya->id)
        ->firstOrFail();
});

it('creates a quick order from the storefront and reserves inventory', function (): void {
    $tenant = Tenant::factory()->create();
    startCheckoutSubscription($tenant);
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'checkout-demo']);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
        'price_minor' => 150000,
    ]);

    InventoryItem::factory()->forProduct($product)->create([
        'quantity' => 10,
        'reserved_quantity' => 1,
    ]);
    ShippingRate::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'delivery_type' => DeliveryType::Home,
        'price_minor' => 50000,
    ]);
    PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->postJson("/api/storefront/{$store->subdomain}/checkout", [
        'full_name' => 'Amine Benali',
        'phone' => '0555123456',
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'address' => 'Rue Didouche Mourad, Alger',
        'delivery_type' => DeliveryType::Home->value,
        'product_id' => $product->id,
        'quantity' => 2,
        'note' => 'Call before delivery',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', OrderStatus::Pending->value)
        ->assertJsonPath('data.payment_status', PaymentStatus::Unpaid->value)
        ->assertJsonPath('data.subtotal_minor', 300000)
        ->assertJsonPath('data.shipping_fee_minor', 50000)
        ->assertJsonPath('data.total_minor', 350000)
        ->assertJsonPath('data.customer.phone', '0555123456')
        ->assertJsonPath('data.items.0.product_id', $product->id)
        ->assertJsonPath('data.items.0.quantity', 2);

    $orderNumber = $response->json('data.order_number');

    $this->assertDatabaseHas('orders', [
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'order_number' => $orderNumber,
        'status' => OrderStatus::Pending->value,
        'total_minor' => 350000,
    ]);
    $this->assertDatabaseHas('payments', [
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::Pending->value,
        'amount_minor' => 350000,
    ]);
    $this->assertDatabaseHas('order_status_histories', [
        'tenant_id' => $tenant->id,
        'from_status' => null,
        'to_status' => OrderStatus::Pending->value,
    ]);

    expect($product->inventoryItem()->first()->reserved_quantity)->toBe(3);
});

it('replays a checkout response for the same idempotency key without creating a second order', function (): void {
    [$tenant, $store, $product] = checkoutScenario($this->wilaya, $this->commune, 'idempotent-repeat');
    $payload = checkoutPayload($product, $this->wilaya, $this->commune);
    $key = 'quick-order-repeat-1';

    $firstResponse = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/storefront/{$store->subdomain}/checkout", $payload);
    $secondResponse = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/storefront/{$store->subdomain}/checkout", $payload);

    $firstResponse->assertCreated();
    $secondResponse
        ->assertCreated()
        ->assertJsonPath('data.id', $firstResponse->json('data.id'))
        ->assertJsonPath('data.order_number', $firstResponse->json('data.order_number'));

    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseHas('checkout_idempotency_records', [
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'order_id' => $firstResponse->json('data.id'),
        'idempotency_key' => $key,
        'response_status' => 201,
    ]);

    expect($product->inventoryItem()->first()->reserved_quantity)->toBe(1);
});

it('rejects an idempotency key reused with a different checkout payload', function (): void {
    [$tenant, $store, $product] = checkoutScenario($this->wilaya, $this->commune, 'idempotent-conflict');
    $payload = checkoutPayload($product, $this->wilaya, $this->commune);
    $key = 'quick-order-conflict-1';

    $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/storefront/{$store->subdomain}/checkout", $payload)
        ->assertCreated();

    $conflictResponse = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/storefront/{$store->subdomain}/checkout", [
            ...$payload,
            'quantity' => 2,
        ]);

    $conflictResponse->assertStatus(409);

    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseHas('checkout_idempotency_records', [
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'idempotency_key' => $key,
    ]);

    expect($product->inventoryItem()->first()->reserved_quantity)->toBe(1);
});

it('keeps checkout idempotency keys isolated by tenant and store', function (): void {
    [$firstTenant, $firstStore, $firstProduct] = checkoutScenario($this->wilaya, $this->commune, 'idempotent-tenant-a');
    [$secondTenant, $secondStore, $secondProduct] = checkoutScenario($this->wilaya, $this->commune, 'idempotent-tenant-b');
    $key = 'shared-key-across-tenants';

    $firstResponse = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/storefront/{$firstStore->subdomain}/checkout", checkoutPayload($firstProduct, $this->wilaya, $this->commune));
    $secondResponse = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/storefront/{$secondStore->subdomain}/checkout", checkoutPayload($secondProduct, $this->wilaya, $this->commune));

    $firstResponse->assertCreated();
    $secondResponse->assertCreated();

    expect($firstResponse->json('data.id'))->not->toBe($secondResponse->json('data.id'));
    $this->assertDatabaseCount('orders', 2);
    expect(CheckoutIdempotencyRecord::query()
        ->withoutGlobalScope('current_tenant')
        ->where('idempotency_key', $key)
        ->pluck('tenant_id')
        ->all())->toContain($firstTenant->id, $secondTenant->id);
});

it('returns the existing order for a duplicate checkout submitted inside the duplicate window', function (): void {
    [$tenant, $store, $product] = checkoutScenario($this->wilaya, $this->commune, 'duplicate-window');
    $payload = checkoutPayload($product, $this->wilaya, $this->commune);

    $firstResponse = $this->postJson("/api/storefront/{$store->subdomain}/checkout", $payload);
    $secondResponse = $this->postJson("/api/storefront/{$store->subdomain}/checkout", $payload);

    $firstResponse->assertCreated();
    $secondResponse
        ->assertOk()
        ->assertJsonPath('data.id', $firstResponse->json('data.id'));

    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseHas('checkout_idempotency_records', [
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'order_id' => $firstResponse->json('data.id'),
        'idempotency_key' => null,
    ]);

    expect($product->inventoryItem()->first()->reserved_quantity)->toBe(1);
});

it('applies an enabled coupon during quick checkout', function (): void {
    $tenant = Tenant::factory()->create();
    startCheckoutSubscription($tenant);
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'coupon-demo']);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
        'price_minor' => 200000,
    ]);
    $coupon = Coupon::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'SAVE10',
        'type' => CouponType::Percentage,
        'value' => 10,
        'max_discount_minor' => 30000,
        'usage_limit' => 5,
        'used_count' => 0,
    ]);

    ShippingRate::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'delivery_type' => DeliveryType::Home,
        'price_minor' => 50000,
    ]);
    PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->postJson("/api/storefront/{$store->subdomain}/checkout", [
        'full_name' => 'Amine Benali',
        'phone' => '0555123456',
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'address' => 'Rue Didouche Mourad, Alger',
        'delivery_type' => DeliveryType::Home->value,
        'product_id' => $product->id,
        'quantity' => 2,
        'coupon_code' => 'save10',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.coupon.code', 'SAVE10')
        ->assertJsonPath('data.subtotal_minor', 400000)
        ->assertJsonPath('data.shipping_fee_minor', 50000)
        ->assertJsonPath('data.discount_minor', 30000)
        ->assertJsonPath('data.total_minor', 420000);

    $orderId = $response->json('data.id');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'tenant_id' => $tenant->id,
        'coupon_id' => $coupon->id,
        'discount_minor' => 30000,
        'total_minor' => 420000,
    ]);
    $this->assertDatabaseHas('coupon_redemptions', [
        'tenant_id' => $tenant->id,
        'coupon_id' => $coupon->id,
        'order_id' => $orderId,
        'code' => 'SAVE10',
        'discount_minor' => 30000,
    ]);

    expect($coupon->fresh()->used_count)->toBe(1);
});

it('rejects coupon usage when the subscription plan does not include coupons', function (): void {
    $tenant = Tenant::factory()->create();
    startCheckoutSubscription($tenant, couponsEnabled: false);
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'coupon-disabled']);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
        'price_minor' => 200000,
    ]);
    $coupon = Coupon::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'SAVE10',
        'type' => CouponType::Percentage,
        'value' => 10,
    ]);

    ShippingRate::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'delivery_type' => DeliveryType::Home,
        'price_minor' => 50000,
    ]);
    PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->postJson("/api/storefront/{$store->subdomain}/checkout", [
        'full_name' => 'Amine Benali',
        'phone' => '0555123456',
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'address' => 'Rue Didouche Mourad, Alger',
        'delivery_type' => DeliveryType::Home->value,
        'product_id' => $product->id,
        'quantity' => 1,
        'coupon_code' => 'SAVE10',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('coupon_code');

    $this->assertDatabaseCount('orders', 0);
    expect($coupon->fresh()->used_count)->toBe(0);
});

it('allows checkout to reserve beyond stock when backorders are enabled', function (): void {
    $tenant = Tenant::factory()->create();
    startCheckoutSubscription($tenant);
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'backorder-demo']);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
        'price_minor' => 100000,
    ]);

    InventoryItem::factory()->forProduct($product)->create([
        'quantity' => 1,
        'reserved_quantity' => 0,
        'allow_backorders' => true,
    ]);
    ShippingRate::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'delivery_type' => DeliveryType::Home,
        'price_minor' => 50000,
    ]);
    PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->postJson("/api/storefront/{$store->subdomain}/checkout", [
        'full_name' => 'Amine Benali',
        'phone' => '0555123456',
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'address' => 'Rue Didouche Mourad, Alger',
        'delivery_type' => DeliveryType::Home->value,
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.items.0.quantity', 3)
        ->assertJsonPath('data.total_minor', 350000);

    expect($product->inventoryItem()->first()->reserved_quantity)->toBe(3);
});

it('rejects checkout when the monthly order limit is reached', function (): void {
    $tenant = Tenant::factory()->create();
    startCheckoutSubscription($tenant, maxOrdersPerMonth: 0);
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'order-limit-demo']);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
        'price_minor' => 100000,
    ]);

    ShippingRate::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'delivery_type' => DeliveryType::Home,
        'price_minor' => 50000,
    ]);
    PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->postJson("/api/storefront/{$store->subdomain}/checkout", [
        'full_name' => 'Amine Benali',
        'phone' => '0555123456',
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'address' => 'Rue Didouche Mourad, Alger',
        'delivery_type' => DeliveryType::Home->value,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(PlanFeatureKey::MaxOrdersPerMonth->value);

    $this->assertDatabaseCount('orders', 0);
});

it('rejects checkout when the product belongs to another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    startCheckoutSubscription($tenant);
    $otherTenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'tenant-checkout']);
    $otherProduct = Product::factory()->create([
        'tenant_id' => $otherTenant->id,
        'status' => ProductStatus::Active,
    ]);

    ShippingRate::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'delivery_type' => DeliveryType::Home,
    ]);
    PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->postJson("/api/storefront/{$store->subdomain}/checkout", [
        'full_name' => 'Amine Benali',
        'phone' => '0555123456',
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'address' => 'Rue Didouche Mourad, Alger',
        'delivery_type' => DeliveryType::Home->value,
        'product_id' => $otherProduct->id,
        'quantity' => 1,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    $this->assertDatabaseCount('orders', 0);
});

it('rejects checkout when shipping is unavailable for the commune and delivery type', function (): void {
    $tenant = Tenant::factory()->create();
    startCheckoutSubscription($tenant);
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'no-shipping']);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
    ]);

    PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->postJson("/api/storefront/{$store->subdomain}/checkout", [
        'full_name' => 'Amine Benali',
        'phone' => '0555123456',
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'address' => 'Rue Didouche Mourad, Alger',
        'delivery_type' => DeliveryType::Desk->value,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('delivery_type');

    $this->assertDatabaseCount('orders', 0);
});

it('tracks an order by order number and customer phone', function (): void {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'track-demo']);
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'phone' => '0555123456',
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
    ]);

    $response = $this->getJson("/api/storefront/{$store->subdomain}/track-order?".http_build_query([
        'order_number' => $order->order_number,
        'phone' => '0555 123 456',
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonPath('data.customer.phone', '0555123456');
});

it('records status history when an order status changes', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Pending,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
    ]);

    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreAdmin->value,
        'permissions' => null,
    ]);

    $this->actingAs($user);

    $order->update(['status' => OrderStatus::Confirmed]);

    $this->assertDatabaseHas('order_status_histories', [
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'from_status' => OrderStatus::Pending->value,
        'to_status' => OrderStatus::Confirmed->value,
        'changed_by_id' => $user->id,
    ]);
});

it('prevents a vendor from viewing another tenant order', function (): void {
    $vendor = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $otherStore = Store::factory()->for($otherTenant)->create();
    $otherCustomer = Customer::factory()->create([
        'tenant_id' => $otherTenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $otherTenant->id,
        'store_id' => $otherStore->id,
        'customer_id' => $otherCustomer->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
    ]);

    $tenant->users()->attach($vendor, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    expect($vendor->can('view', $order))->toBeFalse();
});

function startCheckoutSubscription(Tenant $tenant, int $maxOrdersPerMonth = 1000, bool $couponsEnabled = true): void
{
    $plan = Plan::query()->create([
        'name' => 'Checkout Test',
        'slug' => 'checkout-test-'.str()->random(8),
        'price_minor' => 0,
        'currency' => 'DZD',
        'billing_interval' => 'monthly',
        'is_active' => true,
        'sort_order' => 10,
        'metadata' => [],
    ]);

    PlanFeature::query()->create([
        'plan_id' => $plan->id,
        'key' => PlanFeatureKey::MaxOrdersPerMonth->value,
        'value' => ['value' => $maxOrdersPerMonth],
    ]);

    PlanFeature::query()->create([
        'plan_id' => $plan->id,
        'key' => PlanFeatureKey::Coupons->value,
        'value' => ['value' => $couponsEnabled],
    ]);

    app(StartTenantSubscription::class)->handle($tenant, $plan, createInvoice: false);
}

/**
 * @return array{0: Tenant, 1: Store, 2: Product}
 */
function checkoutScenario(Wilaya $wilaya, Commune $commune, string $subdomain): array
{
    $tenant = Tenant::factory()->create();
    startCheckoutSubscription($tenant);
    $store = Store::factory()->for($tenant)->create(['subdomain' => $subdomain]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
        'price_minor' => 100000,
    ]);

    InventoryItem::factory()->forProduct($product)->create([
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);
    ShippingRate::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'delivery_type' => DeliveryType::Home,
        'price_minor' => 50000,
    ]);
    PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $store, $product];
}

/**
 * @return array<string, mixed>
 */
function checkoutPayload(Product $product, Wilaya $wilaya, Commune $commune, int $quantity = 1, string $phone = '0555123456'): array
{
    return [
        'full_name' => 'Amine Benali',
        'phone' => $phone,
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'address' => 'Rue Didouche Mourad, Alger',
        'delivery_type' => DeliveryType::Home->value,
        'product_id' => $product->id,
        'quantity' => $quantity,
    ];
}
