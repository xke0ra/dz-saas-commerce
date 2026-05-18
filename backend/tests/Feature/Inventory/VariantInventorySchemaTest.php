<?php

use App\Actions\Billing\StartTenantSubscription;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlanFeatureKey;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\Commune;
use App\Models\InventoryItem;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Models\StockMovement;
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

it('allows one simple inventory item per tenant product with null variant', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $inventoryItem = InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => null,
    ]);

    $this->assertDatabaseHas('inventory_items', [
        'id' => $inventoryItem->id,
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'product_variant_id' => null,
    ]);
});

it('rejects duplicate simple inventory item for the same tenant product with null variant', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => null,
    ]);

    InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => null,
    ]);
})->throws(QueryException::class);

it('allows multiple variant inventory items for the same tenant product when variants differ', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $smallVariant = ProductVariant::factory()->forProduct($product)->create([
        'sku' => 'SCHEMA-SMALL',
        'option_signature' => 'size=small',
    ]);
    $largeVariant = ProductVariant::factory()->forProduct($product)->create([
        'sku' => 'SCHEMA-LARGE',
        'option_signature' => 'size=large',
    ]);

    $smallInventory = InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => $smallVariant->id,
        'sku' => $smallVariant->sku,
    ]);
    $largeInventory = InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => $largeVariant->id,
        'sku' => $largeVariant->sku,
    ]);

    expect($smallInventory->product_id)->toBe($product->id)
        ->and($largeInventory->product_id)->toBe($product->id)
        ->and($smallInventory->product_variant_id)->toBe($smallVariant->id)
        ->and($largeInventory->product_variant_id)->toBe($largeVariant->id);
});

it('rejects duplicate inventory item for the same product variant id', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->forProduct($product)->create([
        'sku' => 'SCHEMA-DUP',
        'option_signature' => 'size=dup',
    ]);

    InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => $variant->id,
        'sku' => $variant->sku,
    ]);

    InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => $variant->id,
        'sku' => $variant->sku,
    ]);
})->throws(QueryException::class);

it('rejects inventory item with product variant id from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherVariant = ProductVariant::factory()->forProduct($otherProduct)->create([
        'sku' => 'OTHER-TENANT-VARIANT',
        'option_signature' => 'size=other',
    ]);

    InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => $otherVariant->id,
    ]);
})->throws(QueryException::class);

it('allows checkout to reserve variant inventory when multiple variants exist for the same product', function (): void {
    [$store, $product, $smallVariant, $largeVariant, $smallInventory, $largeInventory] = variantInventorySchemaCheckoutContext(
        $this->wilaya,
        $this->commune,
        'variant-inventory-schema',
    );
    $payload = variantInventorySchemaCheckoutPayload($product, $this->wilaya, $this->commune);
    unset($payload['product_id'], $payload['quantity']);
    $payload['items'] = [[
        'product_id' => $product->id,
        'product_variant_id' => $smallVariant->id,
        'quantity' => 2,
    ]];

    $response = $this->postJson("/api/storefront/{$store->subdomain}/checkout", $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', OrderStatus::Pending->value)
        ->assertJsonPath('data.payment_status', PaymentStatus::Unpaid->value);

    $movement = StockMovement::query()
        ->withoutGlobalScope('current_tenant')
        ->where('product_variant_id', $smallVariant->id)
        ->firstOrFail();

    expect($smallInventory->refresh()->reserved_quantity)->toBe(2)
        ->and($largeInventory->refresh()->reserved_quantity)->toBe(0)
        ->and($largeInventory->product_variant_id)->toBe($largeVariant->id)
        ->and($movement->product_id)->toBe($product->id)
        ->and($movement->product_variant_id)->toBe($smallVariant->id)
        ->and($movement->inventory_item_id)->toBe($smallInventory->id)
        ->and($movement->type)->toBe(StockMovementType::Reserved)
        ->and($movement->reserved_delta)->toBe(2);
});

it('keeps simple product checkout working with product level inventory', function (): void {
    $tenant = Tenant::factory()->create();
    startVariantInventorySchemaSubscription($tenant);
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'simple-inventory-schema']);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
        'price_minor' => 100000,
    ]);
    $inventoryItem = InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => null,
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);

    ShippingRate::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $this->wilaya->id,
        'commune_id' => $this->commune->id,
        'delivery_type' => DeliveryType::Home,
        'price_minor' => 50000,
    ]);
    PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->postJson(
        "/api/storefront/{$store->subdomain}/checkout",
        variantInventorySchemaCheckoutPayload($product, $this->wilaya, $this->commune, quantity: 3),
    );

    $response
        ->assertCreated()
        ->assertJsonPath('data.total_minor', 350000);

    expect($inventoryItem->refresh()->reserved_quantity)->toBe(3);
});

function startVariantInventorySchemaSubscription(Tenant $tenant): void
{
    $plan = Plan::query()->create([
        'name' => 'Variant Inventory Schema Test',
        'slug' => 'variant-inventory-schema-test-'.str()->random(8),
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
        'value' => ['value' => 1000],
    ]);

    PlanFeature::query()->create([
        'plan_id' => $plan->id,
        'key' => PlanFeatureKey::Coupons->value,
        'value' => ['value' => true],
    ]);

    app(StartTenantSubscription::class)->handle($tenant, $plan, createInvoice: false);
}

/**
 * @return array{0: Store, 1: Product, 2: ProductVariant, 3: ProductVariant, 4: InventoryItem, 5: InventoryItem}
 */
function variantInventorySchemaCheckoutContext(Wilaya $wilaya, Commune $commune, string $subdomain): array
{
    $tenant = Tenant::factory()->create();
    startVariantInventorySchemaSubscription($tenant);
    $store = Store::factory()->for($tenant)->create(['subdomain' => $subdomain]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
        'type' => ProductType::Variable,
        'price_minor' => 100000,
    ]);
    $smallVariant = ProductVariant::factory()->forProduct($product)->create([
        'sku' => 'SCHEMA-SMALL-CHK',
        'option_signature' => 'size=small',
        'title' => 'Small',
    ]);
    $largeVariant = ProductVariant::factory()->forProduct($product)->create([
        'sku' => 'SCHEMA-LARGE-CHK',
        'option_signature' => 'size=large',
        'title' => 'Large',
    ]);
    $smallInventory = InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => $smallVariant->id,
        'sku' => $smallVariant->sku,
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);
    $largeInventory = InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => $largeVariant->id,
        'sku' => $largeVariant->sku,
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

    return [$store, $product, $smallVariant, $largeVariant, $smallInventory, $largeInventory];
}

/**
 * @return array<string, mixed>
 */
function variantInventorySchemaCheckoutPayload(Product $product, Wilaya $wilaya, Commune $commune, int $quantity = 1): array
{
    return [
        'full_name' => 'Amine Benali',
        'phone' => '0555123456',
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'address' => 'Rue Didouche Mourad, Alger',
        'delivery_type' => DeliveryType::Home->value,
        'product_id' => $product->id,
        'quantity' => $quantity,
    ];
}
