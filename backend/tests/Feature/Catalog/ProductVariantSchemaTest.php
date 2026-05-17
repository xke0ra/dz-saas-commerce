<?php

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Commune;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Wilaya;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->wilaya = Wilaya::query()->findOrFail(16);
    $this->commune = Commune::query()
        ->where('wilaya_id', $this->wilaya->id)
        ->firstOrFail();
});

it('creates product options values and variants for a tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $optionId = insertProductVariantSchemaOption($tenant, $product, 'Size');
    $valueId = insertProductVariantSchemaValue($tenant, $optionId, 'XL');
    $variantId = insertProductVariantSchemaVariant($tenant, $product, 'size=xl', 'TSHIRT-XL');
    $pivotId = insertProductVariantSchemaPivot($tenant, $variantId, $valueId);

    $this->assertDatabaseHas('product_options', [
        'id' => $optionId,
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'name' => 'Size',
    ]);
    $this->assertDatabaseHas('product_option_values', [
        'id' => $valueId,
        'tenant_id' => $tenant->id,
        'product_option_id' => $optionId,
        'value' => 'XL',
    ]);
    $this->assertDatabaseHas('product_variants', [
        'id' => $variantId,
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'sku' => 'TSHIRT-XL',
        'option_signature' => 'size=xl',
    ]);
    $this->assertDatabaseHas('product_variant_option_values', [
        'id' => $pivotId,
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variantId,
        'product_option_value_id' => $valueId,
    ]);
});

it('enforces option name uniqueness within product', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    insertProductVariantSchemaOption($tenant, $product, 'Color');
    insertProductVariantSchemaOption($tenant, $product, 'Color');
})->throws(QueryException::class);

it('enforces option value uniqueness within option', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $optionId = insertProductVariantSchemaOption($tenant, $product, 'Size');

    insertProductVariantSchemaValue($tenant, $optionId, 'Large');
    insertProductVariantSchemaValue($tenant, $optionId, 'Large');
})->throws(QueryException::class);

it('enforces variant option signature uniqueness within product', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    insertProductVariantSchemaVariant($tenant, $product, 'size=large', 'SKU-L');
    insertProductVariantSchemaVariant($tenant, $product, 'size=large', 'SKU-L-2');
})->throws(QueryException::class);

it('enforces variant sku uniqueness within tenant when sku is not null', function (): void {
    $tenant = Tenant::factory()->create();
    $firstProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
    $secondProduct = Product::factory()->create(['tenant_id' => $tenant->id]);

    insertProductVariantSchemaVariant($tenant, $firstProduct, 'size=small', 'SHARED-SKU');
    insertProductVariantSchemaVariant($tenant, $secondProduct, 'size=medium', 'SHARED-SKU');
})->throws(QueryException::class);

it('allows null sku for multiple variants', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    insertProductVariantSchemaVariant($tenant, $product, 'size=small', null);
    insertProductVariantSchemaVariant($tenant, $product, 'size=medium', null);

    expect(DB::table('product_variants')
        ->where('tenant_id', $tenant->id)
        ->where('product_id', $product->id)
        ->whereNull('sku')
        ->count())->toBe(2);
});

it('rejects product option for product from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);

    insertProductVariantSchemaOption($tenant, $otherProduct, 'Size');
})->throws(QueryException::class);

it('rejects product option value for option from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherOptionId = insertProductVariantSchemaOption($otherTenant, $otherProduct, 'Size');

    insertProductVariantSchemaValue($tenant, $otherOptionId, 'XL');
})->throws(QueryException::class);

it('rejects product variant for product from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);

    insertProductVariantSchemaVariant($tenant, $otherProduct, 'size=xl', 'WRONG-TENANT-SKU');
})->throws(QueryException::class);

it('rejects product variant option value with variant and value from different tenants', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variantId = insertProductVariantSchemaVariant($tenant, $product, 'size=xl', 'TENANT-A-XL');

    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherOptionId = insertProductVariantSchemaOption($otherTenant, $otherProduct, 'Size');
    $otherValueId = insertProductVariantSchemaValue($otherTenant, $otherOptionId, 'XL');

    insertProductVariantSchemaPivot($tenant, $variantId, $otherValueId);
})->throws(QueryException::class);

it('allows nullable product variant id on existing inventory order and stock movement rows', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $inventoryItem = InventoryItem::factory()->forProduct($product)->create([
        'quantity' => 20,
        'reserved_quantity' => 0,
    ]);
    $order = createProductVariantSchemaOrder($tenant, $this->wilaya->id, $this->commune->id);
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
    $movement = StockMovement::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'inventory_item_id' => $inventoryItem->id,
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'type' => StockMovementType::Reserved,
        'quantity_delta' => 0,
        'reserved_delta' => 1,
        'balance_quantity_after' => 20,
        'balance_reserved_after' => 1,
        'reason' => 'schema_foundation_test',
        'metadata' => [],
    ]);

    $this->assertDatabaseHas('inventory_items', [
        'id' => $inventoryItem->id,
        'product_variant_id' => null,
    ]);
    $this->assertDatabaseHas('order_items', [
        'id' => $orderItem->id,
        'product_variant_id' => null,
        'variant_title' => null,
        'variant_sku' => null,
        'selected_options' => null,
    ]);
    $this->assertDatabaseHas('stock_movements', [
        'id' => $movement->id,
        'product_variant_id' => null,
    ]);
});

it('rejects inventory item product variant id from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherVariantId = insertProductVariantSchemaVariant($otherTenant, $otherProduct, 'size=xl', 'OTHER-INV-XL');
    $timestamp = now();

    DB::table('inventory_items')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'product_variant_id' => $otherVariantId,
        'sku' => 'INVALID-INV',
        'quantity' => 10,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 2,
        'track_quantity' => true,
        'allow_backorders' => false,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
})->throws(QueryException::class);

it('rejects order item product variant id from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $order = createProductVariantSchemaOrder($tenant, $this->wilaya->id, $this->commune->id);
    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherVariantId = insertProductVariantSchemaVariant($otherTenant, $otherProduct, 'size=xl', 'OTHER-ORDER-XL');
    $timestamp = now();

    DB::table('order_items')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $otherVariantId,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'variant_title' => null,
        'variant_sku' => null,
        'selected_options' => null,
        'quantity' => 1,
        'unit_price_minor' => 100000,
        'total_minor' => 100000,
        'metadata' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
})->throws(QueryException::class);

it('rejects stock movement product variant id from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $inventoryItem = InventoryItem::factory()->forProduct($product)->create();
    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherVariantId = insertProductVariantSchemaVariant($otherTenant, $otherProduct, 'size=xl', 'OTHER-STOCK-XL');
    $timestamp = now();

    DB::table('stock_movements')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'product_variant_id' => $otherVariantId,
        'inventory_item_id' => $inventoryItem->id,
        'order_id' => null,
        'order_item_id' => null,
        'order_return_id' => null,
        'actor_id' => null,
        'type' => StockMovementType::Correction->value,
        'quantity_delta' => 1,
        'reserved_delta' => 0,
        'balance_quantity_after' => $inventoryItem->quantity + 1,
        'balance_reserved_after' => $inventoryItem->reserved_quantity,
        'reason' => 'invalid_variant_tenant',
        'metadata' => null,
        'occurred_at' => $timestamp,
        'created_at' => $timestamp,
    ]);
})->throws(QueryException::class);

it('migrate fresh builds the product variant schema', function (): void {
    expect(Schema::hasTable('product_options'))->toBeTrue()
        ->and(Schema::hasTable('product_option_values'))->toBeTrue()
        ->and(Schema::hasTable('product_variants'))->toBeTrue()
        ->and(Schema::hasTable('product_variant_option_values'))->toBeTrue()
        ->and(Schema::hasColumns('inventory_items', ['product_variant_id']))->toBeTrue()
        ->and(Schema::hasColumns('order_items', [
            'product_variant_id',
            'variant_title',
            'variant_sku',
            'selected_options',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('stock_movements', ['product_variant_id']))->toBeTrue();
});

function insertProductVariantSchemaOption(Tenant $tenant, Product $product, string $name): string
{
    $id = (string) Str::ulid();
    $timestamp = now();

    DB::table('product_options')->insert([
        'id' => $id,
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'name' => $name,
        'position' => 0,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    return $id;
}

function insertProductVariantSchemaValue(Tenant $tenant, string $optionId, string $value): string
{
    $id = (string) Str::ulid();
    $timestamp = now();

    DB::table('product_option_values')->insert([
        'id' => $id,
        'tenant_id' => $tenant->id,
        'product_option_id' => $optionId,
        'value' => $value,
        'position' => 0,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    return $id;
}

function insertProductVariantSchemaVariant(Tenant $tenant, Product $product, string $signature, ?string $sku): string
{
    $id = (string) Str::ulid();
    $timestamp = now();

    DB::table('product_variants')->insert([
        'id' => $id,
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'sku' => $sku,
        'option_signature' => $signature,
        'title' => null,
        'price_minor' => 100000,
        'compare_at_price_minor' => null,
        'cost_price_minor' => null,
        'status' => ProductStatus::Active->value,
        'sort_order' => 0,
        'metadata' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    return $id;
}

function insertProductVariantSchemaPivot(Tenant $tenant, string $variantId, string $valueId): string
{
    $id = (string) Str::ulid();

    DB::table('product_variant_option_values')->insert([
        'id' => $id,
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variantId,
        'product_option_value_id' => $valueId,
        'created_at' => now(),
    ]);

    return $id;
}

function createProductVariantSchemaOrder(Tenant $tenant, int $wilayaId, int $communeId): Order
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
        'order_number' => 'ORD-VARIANTS-'.Str::upper(Str::random(8)),
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
