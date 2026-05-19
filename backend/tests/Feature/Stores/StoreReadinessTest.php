<?php

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\StoreStatus;
use App\Models\InventoryItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantOptionValue;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Models\StoreSetting;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use App\Support\Readiness\StoreReadinessChecker;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);
});

it('marks a store not ready when no payment method is enabled', function (): void {
    $store = storeReadinessStore(withPayment: false);
    storeReadinessSimpleProduct($store);

    $result = app(StoreReadinessChecker::class)->check($store);

    expect($result->ready())->toBeFalse()
        ->and(storeReadinessErrorCodes($result->toArray()))->toContain(StoreReadinessChecker::MISSING_PAYMENT_METHOD)
        ->and(storeReadinessErrorCodes($result->toArray()))->not->toContain(StoreReadinessChecker::MISSING_SHIPPING_RATE)
        ->and(storeReadinessErrorCodes($result->toArray()))->not->toContain(StoreReadinessChecker::NO_SELLABLE_PRODUCTS);
});

it('marks a store not ready when no shipping rate is active', function (): void {
    $store = storeReadinessStore(withShipping: false);
    storeReadinessSimpleProduct($store);

    $result = app(StoreReadinessChecker::class)->check($store);

    expect($result->ready())->toBeFalse()
        ->and(storeReadinessErrorCodes($result->toArray()))->toContain(StoreReadinessChecker::MISSING_SHIPPING_RATE)
        ->and(storeReadinessErrorCodes($result->toArray()))->not->toContain(StoreReadinessChecker::MISSING_PAYMENT_METHOD)
        ->and(storeReadinessErrorCodes($result->toArray()))->not->toContain(StoreReadinessChecker::NO_SELLABLE_PRODUCTS);
});

it('marks a store not ready when no active published product is sellable', function (): void {
    $store = storeReadinessStore();

    $result = app(StoreReadinessChecker::class)->check($store);

    expect($result->ready())->toBeFalse()
        ->and(storeReadinessErrorCodes($result->toArray()))->toContain(StoreReadinessChecker::NO_SELLABLE_PRODUCTS);
});

it('marks a simple product ready with sellable product-level inventory', function (): void {
    $store = storeReadinessStore();
    $product = storeReadinessSimpleProduct($store);

    $result = app(StoreReadinessChecker::class)->checkProduct($product);

    expect($result->ready())->toBeTrue()
        ->and(storeReadinessErrorCodes($result->toArray()))->toBe([]);
});

it('marks a simple product not ready when tracked inventory is unavailable and backorders are disabled', function (): void {
    $store = storeReadinessStore();
    $product = storeReadinessSimpleProduct($store, inventoryAttributes: [
        'quantity' => 0,
        'reserved_quantity' => 0,
        'track_quantity' => true,
        'allow_backorders' => false,
    ]);

    $result = app(StoreReadinessChecker::class)->checkProduct($product);

    expect($result->ready())->toBeFalse()
        ->and(storeReadinessErrorCodes($result->toArray()))->toContain(StoreReadinessChecker::PRODUCT_MISSING_INVENTORY);
});

it('marks a simple product ready when backorders are enabled', function (): void {
    $store = storeReadinessStore();
    $product = storeReadinessSimpleProduct($store, inventoryAttributes: [
        'quantity' => 0,
        'reserved_quantity' => 0,
        'track_quantity' => true,
        'allow_backorders' => true,
    ]);

    $result = app(StoreReadinessChecker::class)->checkProduct($product);

    expect($result->ready())->toBeTrue();
});

it('marks a variable product not ready without active variants', function (): void {
    $store = storeReadinessStore();
    $product = storeReadinessVariableProduct($store, withVariant: false);

    $result = app(StoreReadinessChecker::class)->checkProduct($product);

    expect($result->ready())->toBeFalse()
        ->and(storeReadinessErrorCodes($result->toArray()))->toContain(StoreReadinessChecker::VARIABLE_PRODUCT_MISSING_VARIANTS);
});

it('marks a variable product not ready when an active variant has no option values', function (): void {
    $store = storeReadinessStore();
    $product = storeReadinessVariableProduct($store, withOptions: false);

    $result = app(StoreReadinessChecker::class)->checkProduct($product);

    expect($result->ready())->toBeFalse()
        ->and(storeReadinessErrorCodes($result->toArray()))->toContain(StoreReadinessChecker::VARIABLE_PRODUCT_MISSING_OPTIONS)
        ->and(storeReadinessErrorCodes($result->toArray()))->not->toContain(StoreReadinessChecker::VARIABLE_PRODUCT_NO_SELLABLE_VARIANTS);
});

it('marks a variable product not ready when no active variant has sellable inventory', function (): void {
    $store = storeReadinessStore();
    $product = storeReadinessVariableProduct($store, inventoryAttributes: [
        'quantity' => 0,
        'reserved_quantity' => 0,
        'track_quantity' => true,
        'allow_backorders' => false,
    ]);

    $result = app(StoreReadinessChecker::class)->checkProduct($product);

    expect($result->ready())->toBeFalse()
        ->and(storeReadinessErrorCodes($result->toArray()))->toContain(StoreReadinessChecker::VARIABLE_PRODUCT_NO_SELLABLE_VARIANTS)
        ->and(storeReadinessErrorCodes($result->toArray()))->not->toContain(StoreReadinessChecker::VARIABLE_PRODUCT_MISSING_OPTIONS);
});

it('marks a variable product ready with active variant option values and sellable variant inventory', function (): void {
    $store = storeReadinessStore();
    $product = storeReadinessVariableProduct($store);

    $result = app(StoreReadinessChecker::class)->checkProduct($product);

    expect($result->ready())->toBeTrue()
        ->and(storeReadinessErrorCodes($result->toArray()))->toBe([]);
});

it('returns stable readiness codes for store and product failures', function (): void {
    $store = storeReadinessStore(withPayment: false, withShipping: false);
    $storeCodes = storeReadinessErrorCodes(app(StoreReadinessChecker::class)->check($store)->toArray());
    $invalidProduct = Product::factory()->make([
        'status' => ProductStatus::Active,
        'type' => ProductType::Simple,
        'price_minor' => -1,
        'published_at' => now()->subMinute(),
    ]);
    $productCodes = storeReadinessErrorCodes(app(StoreReadinessChecker::class)->checkProduct($invalidProduct)->toArray());

    expect($storeCodes)->toContain(
        StoreReadinessChecker::MISSING_PAYMENT_METHOD,
        StoreReadinessChecker::MISSING_SHIPPING_RATE,
        StoreReadinessChecker::NO_SELLABLE_PRODUCTS,
    )
        ->and($productCodes)->toContain(
            StoreReadinessChecker::INVALID_PRODUCT_PRICE,
            StoreReadinessChecker::PRODUCT_MISSING_INVENTORY,
        );
});

it('throws a validation exception with stable readiness message and codes', function (): void {
    $store = storeReadinessStore(withPayment: false, withShipping: false);

    try {
        app(StoreReadinessChecker::class)->assertReady($store);

        $this->fail('Expected store readiness assertion to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['readiness'][0])->toBe('Store is not ready for publishing.')
            ->and($exception->errors()['readiness_codes'])->toContain(
                StoreReadinessChecker::MISSING_PAYMENT_METHOD,
                StoreReadinessChecker::MISSING_SHIPPING_RATE,
                StoreReadinessChecker::NO_SELLABLE_PRODUCTS,
            );
    }
});

function storeReadinessStore(
    bool $withPayment = true,
    bool $withShipping = true,
    bool $withStoreSetting = true,
    bool $withTheme = true,
    array $storeAttributes = [],
): Store {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()
        ->for($tenant)
        ->create([
            'status' => StoreStatus::Active,
            'subdomain' => 'readiness-'.strtolower(str()->random(8)),
            ...$storeAttributes,
        ]);

    if ($withStoreSetting) {
        StoreSetting::factory()->forStore($store)->create();
    }

    if ($withTheme) {
        ThemeSetting::factory()->forStore($store)->create(['is_active' => true]);
    }

    if ($withPayment) {
        PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);
    }

    if ($withShipping) {
        ShippingRate::factory()->create(['tenant_id' => $tenant->id]);
    }

    return $store;
}

function storeReadinessSimpleProduct(Store $store, array $productAttributes = [], ?array $inventoryAttributes = []): Product
{
    $product = Product::factory()->create([
        'tenant_id' => $store->tenant_id,
        'status' => ProductStatus::Active,
        'type' => ProductType::Simple,
        'price_minor' => 100000,
        'published_at' => now()->subMinute(),
        ...$productAttributes,
    ]);

    if ($inventoryAttributes !== null) {
        InventoryItem::factory()->forProduct($product)->create([
            'product_variant_id' => null,
            'quantity' => 5,
            'reserved_quantity' => 0,
            'track_quantity' => true,
            'allow_backorders' => false,
            ...$inventoryAttributes,
        ]);
    }

    return $product;
}

function storeReadinessVariableProduct(
    Store $store,
    bool $withVariant = true,
    bool $withOptions = true,
    ?array $inventoryAttributes = [],
): Product {
    $product = Product::factory()->create([
        'tenant_id' => $store->tenant_id,
        'status' => ProductStatus::Active,
        'type' => ProductType::Variable,
        'price_minor' => 100000,
        'published_at' => now()->subMinute(),
    ]);

    if (! $withVariant) {
        return $product;
    }

    $variant = ProductVariant::factory()->forProduct($product)->create([
        'status' => ProductStatus::Active,
        'option_signature' => 'size=large',
    ]);

    if ($withOptions) {
        $option = ProductOption::factory()->forProduct($product)->create(['name' => 'Size']);
        $value = ProductOptionValue::factory()->forOption($option)->create(['value' => 'Large']);

        ProductVariantOptionValue::factory()
            ->forVariantAndOptionValue($variant, $value)
            ->create();
    }

    if ($inventoryAttributes !== null) {
        InventoryItem::factory()->forProduct($product)->create([
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 5,
            'reserved_quantity' => 0,
            'track_quantity' => true,
            'allow_backorders' => false,
            ...$inventoryAttributes,
        ]);
    }

    return $product;
}

/**
 * @param  array{ready: bool, errors: array<int, array{code: string, message: string}>, warnings: array<int, array{code: string, message: string}>}  $result
 * @return array<int, string>
 */
function storeReadinessErrorCodes(array $result): array
{
    return collect($result['errors'])
        ->pluck('code')
        ->values()
        ->all();
}
