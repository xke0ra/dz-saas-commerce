<?php

use App\Enums\DeliveryType;
use App\Enums\OrderReturnStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Commune;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wilaya;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->wilaya = Wilaya::query()->findOrFail(16);
    $this->commune = Commune::query()
        ->where('wilaya_id', $this->wilaya->id)
        ->firstOrFail();
});

it('creates a tenant-scoped stock movement', function (): void {
    $inventoryItem = InventoryItem::factory()->create([
        'quantity' => 25,
        'reserved_quantity' => 4,
    ]);
    $actor = User::factory()->create();

    $movement = StockMovement::factory()
        ->forInventoryItem($inventoryItem)
        ->create([
            'actor_id' => $actor->id,
            'type' => StockMovementType::Correction,
            'quantity_delta' => 3,
            'reserved_delta' => 0,
            'balance_quantity_after' => 28,
            'balance_reserved_after' => 4,
        ]);

    $this->assertDatabaseHas('stock_movements', [
        'id' => $movement->id,
        'tenant_id' => $inventoryItem->tenant_id,
        'product_id' => $inventoryItem->product_id,
        'inventory_item_id' => $inventoryItem->id,
        'actor_id' => $actor->id,
        'type' => StockMovementType::Correction->value,
    ]);

    expect($movement->tenant_id)->toBe($inventoryItem->tenant_id)
        ->and($movement->product->is($inventoryItem->product))->toBeTrue()
        ->and($movement->inventoryItem->is($inventoryItem))->toBeTrue()
        ->and($movement->actor->is($actor))->toBeTrue()
        ->and($inventoryItem->stockMovements()->whereKey($movement->id)->exists())->toBeTrue()
        ->and($inventoryItem->product->stockMovements()->whereKey($movement->id)->exists())->toBeTrue();
});

it('casts type, metadata, and occurred_at correctly', function (): void {
    $occurredAt = now()->subMinutes(10)->micro(0);

    $movement = StockMovement::factory()->create([
        'type' => StockMovementType::Reserved,
        'metadata' => ['source' => 'test', 'batch' => 123],
        'occurred_at' => $occurredAt,
    ])->refresh();

    expect($movement->type)->toBe(StockMovementType::Reserved)
        ->and($movement->metadata)->toMatchArray(['source' => 'test', 'batch' => 123])
        ->and($movement->occurred_at->equalTo($occurredAt))->toBeTrue();
});

it('requires at least one non-zero delta', function (): void {
    StockMovement::factory()->create([
        'quantity_delta' => 0,
        'reserved_delta' => 0,
    ]);
})->throws(QueryException::class);

it('rejects stock movement whose product belongs to another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $inventoryItem = InventoryItem::factory()->forProduct($product)->create();

    StockMovement::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $otherProduct->id,
        'inventory_item_id' => $inventoryItem->id,
        'type' => StockMovementType::Correction,
        'quantity_delta' => 1,
        'metadata' => [],
    ]);
})->throws(QueryException::class);

it('rejects stock movement whose inventory item belongs to another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherInventoryItem = InventoryItem::factory()->forProduct($otherProduct)->create();

    StockMovement::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'inventory_item_id' => $otherInventoryItem->id,
        'type' => StockMovementType::Correction,
        'quantity_delta' => 1,
        'metadata' => [],
    ]);
})->throws(QueryException::class);

it('rejects stock movement whose order belongs to another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $inventoryItem = InventoryItem::factory()
        ->forProduct(Product::factory()->create(['tenant_id' => $tenant->id]))
        ->create();
    [$otherOrder] = createStockMovementLedgerOrder(
        tenant: $otherTenant,
        product: Product::factory()->create(['tenant_id' => $otherTenant->id]),
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
    );

    StockMovement::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $inventoryItem->product_id,
        'inventory_item_id' => $inventoryItem->id,
        'order_id' => $otherOrder->id,
        'type' => StockMovementType::Released,
        'reserved_delta' => -1,
        'metadata' => [],
    ]);
})->throws(QueryException::class);

it('rejects stock movement whose order item belongs to another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $inventoryItem = InventoryItem::factory()
        ->forProduct(Product::factory()->create(['tenant_id' => $tenant->id]))
        ->create();
    [, $otherOrderItem] = createStockMovementLedgerOrder(
        tenant: $otherTenant,
        product: Product::factory()->create(['tenant_id' => $otherTenant->id]),
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
    );

    StockMovement::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $inventoryItem->product_id,
        'inventory_item_id' => $inventoryItem->id,
        'order_item_id' => $otherOrderItem->id,
        'type' => StockMovementType::Released,
        'reserved_delta' => -1,
        'metadata' => [],
    ]);
})->throws(QueryException::class);

it('rejects stock movement whose order return belongs to another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $inventoryItem = InventoryItem::factory()
        ->forProduct(Product::factory()->create(['tenant_id' => $tenant->id]))
        ->create();
    [, , $otherOrderReturn] = createStockMovementLedgerOrder(
        tenant: $otherTenant,
        product: Product::factory()->create(['tenant_id' => $otherTenant->id]),
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
    );

    StockMovement::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $inventoryItem->product_id,
        'inventory_item_id' => $inventoryItem->id,
        'order_return_id' => $otherOrderReturn->id,
        'type' => StockMovementType::ReturnReceived,
        'quantity_delta' => 1,
        'metadata' => [],
    ]);
})->throws(QueryException::class);

it('scopes stock movement queries to current tenant using BelongsToTenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $inventoryItem = InventoryItem::factory()
        ->forProduct(Product::factory()->create(['tenant_id' => $tenant->id]))
        ->create();
    $movement = StockMovement::factory()->forInventoryItem($inventoryItem)->create();
    StockMovement::factory()
        ->forInventoryItem(InventoryItem::factory()
            ->forProduct(Product::factory()->create(['tenant_id' => $otherTenant->id]))
            ->create())
        ->create();

    $currentTenant = app(CurrentTenant::class);
    $currentTenant->set($tenant);

    try {
        expect(StockMovement::query()->pluck('id')->all())->toBe([$movement->id]);
    } finally {
        $currentTenant->forget();
    }
});

it('can store balance snapshots after movement', function (): void {
    $movement = StockMovement::factory()->create([
        'quantity_delta' => -2,
        'reserved_delta' => -2,
        'balance_quantity_after' => 18,
        'balance_reserved_after' => 3,
    ])->refresh();

    expect($movement->balance_quantity_after)->toBe(18)
        ->and($movement->balance_reserved_after)->toBe(3);
});

/**
 * @return array{0: Order, 1: OrderItem, 2: OrderReturn}
 */
function createStockMovementLedgerOrder(Tenant $tenant, Product $product, int $wilayaId, int $communeId): array
{
    $store = Store::factory()->for($tenant)->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
    ]);
    $order = Order::query()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'order_number' => 'ORD-LEDGER-'.strtoupper(str()->random(8)),
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
    $orderItem = OrderItem::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'quantity' => 1,
        'unit_price_minor' => 100000,
        'total_minor' => 100000,
        'metadata' => [],
    ]);
    $orderReturn = OrderReturn::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'return_number' => 'RET-LEDGER-'.strtoupper(str()->random(8)),
        'status' => OrderReturnStatus::Requested,
        'reason' => 'Ledger constraint fixture.',
        'requested_at' => now(),
        'metadata' => [],
    ]);

    return [$order, $orderItem, $orderReturn];
}
