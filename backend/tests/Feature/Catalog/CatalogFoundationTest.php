<?php

use App\Enums\CategoryStatus;
use App\Enums\ProductStatus;
use App\Enums\TenantRole;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;

it('scopes catalog queries to the current tenant when tenant context is set', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    Product::factory()->create(['tenant_id' => $otherTenant->id]);

    $currentTenant = app(CurrentTenant::class);
    $currentTenant->set($tenant);

    try {
        expect(Product::query()->pluck('id')->all())->toBe([$product->id]);
    } finally {
        $currentTenant->forget();
    }
});

it('prevents a vendor from viewing another tenant product', function (): void {
    $vendor = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $otherTenant->id]);

    $tenant->users()->attach($vendor, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);

    expect($vendor->can('view', $product))->toBeFalse();
});

it('exposes only active storefront products for the requested store tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'catalog-demo']);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'slug' => 'phones',
        'status' => CategoryStatus::Active,
    ]);
    $visibleProduct = Product::factory()->forCategory($category)->create([
        'slug' => 'iphone-15',
        'status' => ProductStatus::Active,
        'is_featured' => true,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->id,
        'slug' => 'draft-product',
        'status' => ProductStatus::Draft,
    ]);
    Product::factory()->create([
        'tenant_id' => $otherTenant->id,
        'slug' => 'other-tenant-product',
        'status' => ProductStatus::Active,
    ]);
    ProductImage::factory()->forProduct($visibleProduct)->create();
    InventoryItem::factory()->forProduct($visibleProduct)->create([
        'quantity' => 10,
        'reserved_quantity' => 2,
    ]);

    $response = $this->getJson("/api/storefront/{$store->subdomain}/products");

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', $visibleProduct->id)
        ->assertJsonPath('data.0.inventory.available_quantity', 8)
        ->assertJsonCount(1, 'data');
});

it('exposes active categories for the requested store tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'category-demo']);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'slug' => 'fashion',
        'status' => CategoryStatus::Active,
    ]);
    Category::factory()->create([
        'tenant_id' => $tenant->id,
        'slug' => 'inactive-category',
        'status' => CategoryStatus::Inactive,
    ]);
    Category::factory()->create([
        'tenant_id' => $otherTenant->id,
        'slug' => 'other-category',
        'status' => CategoryStatus::Active,
    ]);

    $response = $this->getJson("/api/storefront/{$store->subdomain}/categories");

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', $category->id)
        ->assertJsonCount(1, 'data');
});

it('builds tenant scoped storefront search documents for products', function (): void {
    $tenant = Tenant::factory()->create();
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Phones',
    ]);
    $product = Product::factory()->forCategory($category)->create([
        'name' => 'Algerian Smartphone',
        'sku' => 'DZ-PHONE-01',
        'status' => ProductStatus::Active,
        'published_at' => now()->subMinute(),
    ]);
    $draftProduct = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Draft,
    ]);
    $futureProduct = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
        'published_at' => now()->addDay(),
    ]);

    $document = $product->load('category')->toSearchableArray();

    expect($product->shouldBeSearchable())->toBeTrue()
        ->and($document['tenant_id'])->toBe($tenant->id)
        ->and($document['category_id'])->toBe($category->id)
        ->and($document['category_name'])->toBe('Phones')
        ->and($document['name'])->toBe('Algerian Smartphone')
        ->and($document['sku'])->toBe('DZ-PHONE-01')
        ->and($document['status'])->toBe(ProductStatus::Active->value)
        ->and($document['published_at_timestamp'])->toBe($product->published_at->timestamp)
        ->and($draftProduct->shouldBeSearchable())->toBeFalse()
        ->and($futureProduct->shouldBeSearchable())->toBeFalse();
});

it('searches storefront products without leaking another tenant products', function (): void {
    config(['scout.driver' => 'collection']);

    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create(['subdomain' => 'search-demo']);

    $matchingProduct = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kabyle Olive Oil',
        'sku' => 'DZ-OLIVE-01',
        'status' => ProductStatus::Active,
        'published_at' => now()->subMinute(),
        'is_featured' => true,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Draft Olive Product',
        'status' => ProductStatus::Draft,
    ]);
    Product::factory()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Kabyle Olive Oil Other Tenant',
        'status' => ProductStatus::Active,
    ]);

    $response = $this->getJson("/api/storefront/{$store->subdomain}/search?q=olive");

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', $matchingProduct->id)
        ->assertJsonCount(1, 'data');
});

it('keeps only one primary image per product', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $firstImage = ProductImage::factory()->forProduct($product)->create([
        'path' => 'tenant-products/demo/first.jpg',
        'is_primary' => true,
    ]);
    $secondImage = ProductImage::factory()->forProduct($product)->create([
        'path' => 'tenant-products/demo/second.jpg',
        'is_primary' => true,
    ]);

    expect($firstImage->fresh()->is_primary)->toBeFalse()
        ->and($secondImage->fresh()->is_primary)->toBeTrue();
});
