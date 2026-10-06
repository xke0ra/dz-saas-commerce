<?php

use App\Actions\Checkout\CreateQuickOrder;
use App\Actions\Inventory\AdjustInventoryManually;
use App\Actions\Inventory\ReleaseOrderInventoryReservations;
use App\Actions\Inventory\RestockOrderReturn;
use App\Actions\Inventory\SettleOrderInventory;
use App\Data\Checkout\QuickOrderData;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App.Models\OrderReturn;
use App\Models\PaymentMethod;
use App\Models\Product;
use App.Models\ProductVariant;
use App.Models\ShippingRate;
use App.Models\Store;
use App.Models\Tenant;
use App.Models\User;
use App.Models\Wilaya;
use App.Models\Commune;
use App\Support\Tenancy\CurrentTenant;
use App\Models\StockMovement;
use App\Support\Inventory\StockMovementRequestHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('generates deterministic request_hash for stock movements', function (): void {
    $hasher = new StockMovementRequestHasher();

    $payload1 = [
        'tenant_id' => 'tenant1',
        'product_id' => 'prod1',
        'inventory_item_id' => 'inv1',
        'type' => 'reserved',
        'quantity_delta' => 0,
        'reserved_delta' => 5,
        'reason' => 'test',
    ];

    $payload2 = [
        'tenant_id' => 'tenant1',
        'product_id' => 'prod1',
        'inventory_item_id' => 'inv1',
        'type' => 'reserved',
        'quantity_delta' => 0,
        'reserved_delta' => 5,
        'reason' => 'test',
    ];

    // Same payload should produce same hash
    expect($hasher->hash($payload1))->toBe($hasher->hash($payload2));
    expect($hasher->hash($payload1))->toHaveLength(64);
});

it('generates different hashes for different payloads', function (): void {
    $hasher = new StockMovementRequestHasher();

    $payload1 = [
        'tenant_id' => 'tenant1',
        'product_id' => 'prod1',
        'inventory_item_id' => 'inv1',
        'type' => 'reserved',
        'quantity_delta' => 0,
        'reserved_delta' => 5,
        'reason' => 'test',
    ];

    $payload2 = [
        'tenant_id' => 'tenant1',
        'product_id' => 'prod1',
        'inventory_item_id' => 'inv1',
        'type' => 'reserved',
        'quantity_delta' => 0,
        'reserved_delta' => 3, // Different quantity
        'reason' => 'test',
    ];

    // Different payload should produce different hash
    expect($hasher->hash($payload1))->not->toBe($hasher->hash($payload2));
});

it('enforces request_hash uniqueness per tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
    ]);
    $inventoryItem = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'track_quantity' => true,
    ]);

    // Create first stock movement
    $payload = [
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'inventory_item_id' => $inventoryItem->id,
        'type' => StockMovementType::Reserved->value,
        'quantity_delta' => 0,
        'reserved_delta' => 5,
        'reason' => 'test_reservation',
    ];

    $hash = StockMovement::generateRequestHash($payload);

    DB::table('stock_movements')->insert([
        'id' => \Illuminate\Support\Str::ulid(),
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'inventory_item_id' => $inventoryItem->id,
        'type' => StockMovementType::Reserved->value,
        'quantity_delta' => 0,
        'reserved_delta' => 5,
        'balance_quantity_after' => 100,
        'balance_reserved_after' => 5,
        'reason' => 'test_reservation',
        'request_hash' => $hash,
        'occurred_at' => now(),
        'created_at' => now(),
    ]);

    // Try to insert duplicate with same request_hash - should fail
    expect(fn () => DB::table('stock_movements')->insert([
        'id' => \Illuminate\Support\Str::ulid(),
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'inventory_item_id' => $inventoryItem->id,
        'type' => StockMovementType::Reserved->value,
        'quantity_delta' => 0,
        'reserved_delta' => 5,
        'balance_quantity_after' => 100,
        'balance_reserved_after' => 5,
        'reason' => 'test_reservation',
        'request_hash' => $hash,
        'occurred_at' => now(),
        'created_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows same request_hash for different tenants', function (): void {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $product1 = Product::factory()->create([
        'tenant_id' => $tenant1->id,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
    ]);
    $product2 = Product::factory()->create([
        'tenant_id' => $tenant2->id,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
    ]);
    $inventoryItem1 = InventoryItem::factory()->create([
        'tenant_id' => $tenant1->id,
        'product_id' => $product1->id,
        'track_quantity' => true,
    ]);
    $inventoryItem2 = InventoryItem::factory()->create([
        'tenant_id' => $tenant2->id,
        'product_id' => $product2->id,
        'track_quantity' => true,
    ]);

    $payload = [
        'tenant_id' => $tenant1->id,
        'product_id' => $product1->id,
        'inventory_item_id' => $inventoryItem1->id,
        'type' => StockMovementType::Reserved->value,
        'quantity_delta' => 0,
        'reserved_delta' => 5,
        'reason' => 'test_reservation',
    ];

    $hash = StockMovement::generateRequestHash($payload);

    // Insert for tenant1
    DB::table('stock_movements')->insert([
        'id' => \Illuminate\Support\Str::ulid(),
        'tenant_id' => $tenant1->id,
        'product_id' => $product1->id,
        'inventory_item_id' => $inventoryItem1->id,
        'type' => StockMovementType::Reserved->value,
        'quantity_delta' => 0,
        'reserved_delta' => 5,
        'balance_quantity_after' => 100,
        'balance_reserved_after' => 5,
        'reason' => 'test_reservation',
        'request_hash' => $hash,
        'occurred_at' => now(),
        'created_at' => now(),
    ]);

    // Insert for tenant2 with SAME hash - should succeed (different tenant)
    DB::table('stock_movements')->insert([
        'id' => \Illuminate\Support\Str::ulid(),
        'tenant_id' => $tenant2->id,
        'product_id' => $product2->id,
        'inventory_item_id' => $inventoryItem2->id,
        'type' => StockMovementType::Reserved->value,
        'quantity_delta' => 0,
        'reserved_delta' => 5,
        'balance_quantity_after' => 100,
        'balance_reserved_after' => 5,
        'reason' => 'test_reservation',
        'request_hash' => $hash,
        'occurred_at' => now(),
        'created_at' => now(),
    ]);

    expect(DB::table('stock_movements')->where('request_hash', $hash)->count())->toBe(2);
});

function createTestOrder($store, $product, $customer, $paymentMethod, $shippingRate, $quantity = 2)
{
    return app(CreateQuickOrder::class)->handle($store, new QuickOrderData(
        items: [
            ['product_id' => $product->id, 'quantity' => $quantity],
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

function createTestSetup($quantity = 2)
{
    $wilaya = Wilaya::create([
        'id' => '01',
        'name_ar' => 'Test Wilaya',
        'name_fr' => 'Test Wilaya',
        'is_active' => true,
    ]);
    $commune = Commune::create([
        'wilaya_id' => $wilaya->id,
        'name_ar' => 'Test Commune',
        'name_fr' => 'Test Commune',
        'postal_code' => '16000',
        'is_active' => true,
    ]);

    $tenant = Tenant::factory()->create();
    app(CurrentTenant::class)->set($tenant);

    $store = Store::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $store->tenant_id,
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
    ]);
    $product = Product::factory()->create([
        'tenant_id' => $store->tenant_id,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
    ]);
    InventoryItem::factory()->create([
        'tenant_id' => $store->tenant_id,
        'product_id' => $product->id,
        'quantity' => 100,
        'track_quantity' => true,
    ]);
    $paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $store->tenant_id,
        'type' => PaymentMethodType::CashOnDelivery,
        'is_active' => true,
    ]);
    $shippingRate = ShippingRate::factory()->create([
        'tenant_id' => $store->tenant_id,
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'delivery_type' => DeliveryType::Home,
        'is_active' => true,
    ]);

    $order = createTestOrder($store, $product, $customer, $paymentMethod, $shippingRate);

    return [
        'store' => $store,
        'customer' => $customer,
        'product' => $product,
        'paymentMethod' => $paymentMethod,
        'shippingRate' => $shippingRate,
        'order' => $order,
    ];
}

it('populates request_hash on quick checkout reservation', function (): void {
    $setup = createTestSetup();
    $movement = DB::table('stock_movements')
        ->where('order_id', $setup['order']->id)
        ->where('type', StockMovementType::Reserved->value)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->request_hash)->toHaveLength(64);
    expect($movement->tenant_id)->toBe($setup['store']->tenant_id);
});

it('populates request_hash on manual inventory adjustment', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
    ]);
    $inventoryItem = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'quantity' => 100,
        'track_quantity' => true,
    ]);
    $actor = User::factory()->create([
        'platform_role' => PlatformRole::SuperAdmin,
    ]);

    app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 10,
        reservedDelta: 0,
        reason: 'test adjustment',
        type: StockMovementType::ManualAdjustment,
    );

    $movement = DB::table('stock_movements')
        ->where('tenant_id', $tenant->id)
        ->where('type', StockMovementType::ManualAdjustment->value)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->request_hash)->toHaveLength(64);
    expect($movement->tenant_id)->toBe($tenant->id);
});

it('populates request_hash on order inventory settlement', function (): void {
    $setup = createTestSetup();
    app(SettleOrderInventory::class)->handle($setup['order']);

    $movement = DB::table('stock_movements')
        ->where('order_id', $setup['order']->id)
        ->where('type', StockMovementType::Settled->value)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->request_hash)->toHaveLength(64);
    expect($movement->tenant_id)->toBe($setup['store']->tenant_id);
});

it('populates request_hash on order inventory release', function (): void {
    $setup = createTestSetup();
    app(ReleaseOrderInventoryReservations::class)->handle($setup['order']);

    $movement = DB::table('stock_movements')
        ->where('order_id', $setup['order']->id)
        ->where('type', StockMovementType::Released->value)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->request_hash)->toHaveLength(64);
    expect($movement->tenant_id)->toBe($setup['store']->tenant_id);
});

it('populates request_hash on order return restock', function (): void {
    $setup = createTestSetup();

    // Settle first
    app(SettleOrderInventory::class)->handle($setup['order']);

    // Create order return
    $orderReturn = OrderReturn::factory()->create([
        'tenant_id' => $setup['store']->tenant_id,
        'order_id' => $setup['order']->id,
        'customer_id' => $setup['customer']->id,
        'status' => 'requested',
    ]);
    OrderItem::factory()->create([
        'tenant_id' => $setup['store']->tenant_id,
        'order_id' => $setup['order']->id,
        'product_id' => $setup['product']->id,
        'quantity' => 2,
    ]);

    // Restock the return
    app(RestockOrderReturn::class)->handle($orderReturn);

    $movement = DB::table('stock_movements')
        ->where('order_return_id', $orderReturn->id)
        ->where('type', StockMovementType::Restocked->value)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->request_hash)->toHaveLength(64);
    expect($movement->tenant_id)->toBe($setup['store']->tenant_id);
});

it('rejects duplicate request_hash within same tenant for manual adjustment', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
    ]);
    $inventoryItem = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'quantity' => 100,
        'track_quantity' => true,
    ]);
    $actor = User::factory()->create([
        'platform_role' => PlatformRole::SuperAdmin,
    ]);

    // First adjustment
    app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 10,
        reservedDelta: 0,
        reason: 'test adjustment',
        type: StockMovementType::ManualAdjustment,
    );

    // Second identical adjustment should fail due to unique request_hash
    expect(fn () => app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 10,
        reservedDelta: 0,
        reason: 'test adjustment',
        type: StockMovementType::ManualAdjustment,
    ))->toThrow(\Illuminate\Database\QueryException::class);
});