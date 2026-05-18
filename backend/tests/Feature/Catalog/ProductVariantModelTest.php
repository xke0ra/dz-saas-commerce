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
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantOptionValue;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Wilaya;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\AlgeriaGeographySeeder;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->wilaya = Wilaya::query()->findOrFail(16);
    $this->commune = Commune::query()
        ->where('wilaya_id', $this->wilaya->id)
        ->firstOrFail();
});

it('product has many options and variants', function (): void {
    $product = Product::factory()->create();
    $color = ProductOption::factory()->forProduct($product)->create([
        'name' => 'Color',
        'position' => 2,
    ]);
    $size = ProductOption::factory()->forProduct($product)->create([
        'name' => 'Size',
        'position' => 1,
    ]);
    $large = ProductVariant::factory()->forProduct($product)->create([
        'option_signature' => 'size=large',
        'sort_order' => 2,
    ]);
    $small = ProductVariant::factory()->forProduct($product)->create([
        'option_signature' => 'size=small',
        'sort_order' => 1,
    ]);

    expect($product->options()->pluck('id')->all())->toBe([$size->id, $color->id])
        ->and($product->variants()->pluck('id')->all())->toBe([$small->id, $large->id]);
});

it('option belongs to product and has ordered values', function (): void {
    $product = Product::factory()->create();
    $option = ProductOption::factory()->forProduct($product)->create();
    $large = ProductOptionValue::factory()->forOption($option)->create([
        'value' => 'Large',
        'position' => 2,
    ]);
    $small = ProductOptionValue::factory()->forOption($option)->create([
        'value' => 'Small',
        'position' => 1,
    ]);

    expect($option->product->is($product))->toBeTrue()
        ->and($option->values()->pluck('id')->all())->toBe([$small->id, $large->id]);
});

it('option value belongs to option', function (): void {
    $option = ProductOption::factory()->create();
    $value = ProductOptionValue::factory()->forOption($option)->create();

    expect($value->option->is($option))->toBeTrue();
});

it('variant belongs to product and has option values', function (): void {
    $product = Product::factory()->create();
    $option = ProductOption::factory()->forProduct($product)->create();
    $value = ProductOptionValue::factory()->forOption($option)->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    ProductVariantOptionValue::factory()
        ->forVariantAndOptionValue($variant, $value)
        ->create();

    expect($variant->product->is($product))->toBeTrue()
        ->and($variant->optionValues()->pluck('product_option_values.id')->all())->toBe([$value->id])
        ->and($value->variants()->pluck('product_variants.id')->all())->toBe([$variant->id]);
});

it('variant casts status metadata and prices', function (): void {
    $variant = ProductVariant::factory()->create([
        'status' => ProductStatus::Draft,
        'price_minor' => 123000,
        'compare_at_price_minor' => 150000,
        'cost_price_minor' => 90000,
        'metadata' => ['source' => 'model-test'],
    ])->refresh();

    expect($variant->status)->toBe(ProductStatus::Draft)
        ->and($variant->metadata)->toMatchArray(['source' => 'model-test'])
        ->and($variant->price_minor)->toBe(123000)
        ->and($variant->compare_at_price_minor)->toBe(150000)
        ->and($variant->cost_price_minor)->toBe(90000);
});

it('variant effective price returns override or product price', function (): void {
    $product = Product::factory()->create(['price_minor' => 150000]);
    $inherited = ProductVariant::factory()->forProduct($product)->create([
        'option_signature' => 'size=small',
    ]);
    $overridden = ProductVariant::factory()
        ->forProduct($product)
        ->withPriceOverride(175000)
        ->create([
            'option_signature' => 'size=large',
        ]);

    expect($inherited->effectivePriceMinor())->toBe(150000)
        ->and($overridden->effectivePriceMinor())->toBe(175000);
});

it('inventory item belongs to nullable product variant', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $variantInventory = InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => $variant->id,
    ]);
    $simpleProduct = Product::factory()->create();
    $simpleInventory = InventoryItem::factory()->forProduct($simpleProduct)->create();

    expect($variantInventory->productVariant->is($variant))->toBeTrue()
        ->and($simpleInventory->productVariant)->toBeNull();
});

it('order item belongs to nullable product variant and casts selected options', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create([
        'title' => 'Large / Black',
        'sku' => 'MODEL-L-BLK',
    ]);
    $order = createProductVariantModelOrder($product->tenant, $this->wilaya->id, $this->commune->id);

    $orderItem = OrderItem::query()->create([
        'tenant_id' => $product->tenant_id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'variant_title' => $variant->title,
        'variant_sku' => $variant->sku,
        'selected_options' => ['Size' => 'Large', 'Color' => 'Black'],
        'quantity' => 1,
        'unit_price_minor' => 150000,
        'total_minor' => 150000,
        'metadata' => [],
    ])->refresh();

    expect($orderItem->productVariant->is($variant))->toBeTrue()
        ->and($orderItem->selected_options)->toMatchArray([
            'Size' => 'Large',
            'Color' => 'Black',
        ]);
});

it('stock movement belongs to nullable product variant', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $inventoryItem = InventoryItem::factory()->forProduct($product)->create([
        'product_variant_id' => $variant->id,
    ]);

    $movement = StockMovement::factory()->forInventoryItem($inventoryItem)->create([
        'product_variant_id' => $variant->id,
        'type' => StockMovementType::Correction,
        'quantity_delta' => 1,
    ]);

    expect($movement->productVariant->is($variant))->toBeTrue();
});

it('factories create tenant consistent option value variant records', function (): void {
    $product = Product::factory()->create();
    $option = ProductOption::factory()->forProduct($product)->create();
    $value = ProductOptionValue::factory()->forOption($option)->create();
    $variant = ProductVariant::factory()
        ->forProduct($product)
        ->withSku('TENANT-CONSISTENT-SKU')
        ->create();
    $pivot = ProductVariantOptionValue::factory()
        ->forVariantAndOptionValue($variant, $value)
        ->create();

    expect($option->tenant_id)->toBe($product->tenant_id)
        ->and($value->tenant_id)->toBe($product->tenant_id)
        ->and($variant->tenant_id)->toBe($product->tenant_id)
        ->and($pivot->tenant_id)->toBe($product->tenant_id)
        ->and($pivot->variant->is($variant))->toBeTrue()
        ->and($pivot->optionValue->is($value))->toBeTrue();
});

it('scopes product variant records to the current tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $option = ProductOption::factory()->forProduct($product)->create();
    $value = ProductOptionValue::factory()->forOption($option)->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherOption = ProductOption::factory()->forProduct($otherProduct)->create();
    ProductOptionValue::factory()->forOption($otherOption)->create();
    ProductVariant::factory()->forProduct($otherProduct)->create();

    $currentTenant = app(CurrentTenant::class);
    $currentTenant->set($tenant);

    try {
        expect(ProductOption::query()->pluck('id')->all())->toBe([$option->id])
            ->and(ProductOptionValue::query()->pluck('id')->all())->toBe([$value->id])
            ->and(ProductVariant::query()->pluck('id')->all())->toBe([$variant->id]);
    } finally {
        $currentTenant->forget();
    }
});

function createProductVariantModelOrder(Tenant $tenant, int $wilayaId, int $communeId): Order
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
        'order_number' => 'ORD-VARIANT-MODEL-'.strtoupper(str()->random(8)),
        'status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'delivery_type' => DeliveryType::Home,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
        'shipping_address' => 'Alger Centre',
        'subtotal_minor' => 150000,
        'shipping_fee_minor' => 50000,
        'discount_minor' => 0,
        'total_minor' => 200000,
        'currency' => 'DZD',
        'metadata' => [],
    ]);
}
