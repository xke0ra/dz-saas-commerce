<?php

use App\Enums\ProductStatus;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantOptionValue;
use App\Models\Store;
use App\Models\Tenant;

describe('StorefrontProductVariantResponseTest', function (): void {
    it('includes active variants with selected options and picker options on product detail', function (): void {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->for($tenant)->create(['subdomain' => 'variant-detail']);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'variant-shirt',
            'price_minor' => 100000,
            'status' => ProductStatus::Active,
            'published_at' => now()->subMinute(),
        ]);

        $size = ProductOption::factory()->forProduct($product)->create([
            'name' => 'Size',
            'position' => 0,
        ]);
        $large = ProductOptionValue::factory()->forOption($size)->create([
            'value' => 'Large',
            'position' => 0,
        ]);
        $small = ProductOptionValue::factory()->forOption($size)->create([
            'value' => 'Small',
            'position' => 1,
        ]);
        $color = ProductOption::factory()->forProduct($product)->create([
            'name' => 'Color',
            'position' => 1,
        ]);
        $black = ProductOptionValue::factory()->forOption($color)->create([
            'value' => 'Black',
            'position' => 0,
        ]);

        $activeVariant = ProductVariant::factory()->forProduct($product)->create([
            'sku' => 'VAR-L-BLK',
            'title' => 'Large Black',
            'option_signature' => 'size=large;color=black',
            'price_minor' => 120000,
            'compare_at_price_minor' => 140000,
            'cost_price_minor' => 70000,
            'status' => ProductStatus::Active,
            'sort_order' => 0,
            'metadata' => ['internal' => 'hidden'],
        ]);
        ProductVariantOptionValue::factory()->forVariantAndOptionValue($activeVariant, $large)->create();
        ProductVariantOptionValue::factory()->forVariantAndOptionValue($activeVariant, $black)->create();

        $draftVariant = ProductVariant::factory()->forProduct($product)->create([
            'sku' => 'VAR-S-BLK',
            'option_signature' => 'size=small;color=black',
            'status' => ProductStatus::Draft,
            'sort_order' => 1,
        ]);
        ProductVariantOptionValue::factory()->forVariantAndOptionValue($draftVariant, $small)->create();
        ProductVariantOptionValue::factory()->forVariantAndOptionValue($draftVariant, $black)->create();

        InventoryItem::factory()->forProduct($product)->create([
            'product_variant_id' => $activeVariant->id,
            'quantity' => 8,
            'reserved_quantity' => 3,
            'track_quantity' => true,
            'allow_backorders' => false,
        ]);

        $response = $this->getJson("/api/storefront/{$store->subdomain}/products/{$product->slug}");

        $response->assertOk();

        $data = $response->json('data');
        $variant = $data['variants'][0];
        $optionValues = collect($data['options'])
            ->flatMap(fn (array $option): array => collect($option['values'])->pluck('value')->all())
            ->values()
            ->all();

        expect($data['variants'])->toHaveCount(1)
            ->and($variant['id'])->toBe($activeVariant->id)
            ->and($variant['sku'])->toBe('VAR-L-BLK')
            ->and($variant['title'])->toBe('Large Black')
            ->and($variant['option_signature'])->toBe('size=large;color=black')
            ->and($variant['price_minor'])->toBe(120000)
            ->and($variant['compare_at_price_minor'])->toBe(140000)
            ->and($variant['effective_price_minor'])->toBe(120000)
            ->and($variant['status'])->toBe(ProductStatus::Active->value)
            ->and($variant['available_quantity'])->toBe(5)
            ->and($variant['is_available'])->toBeTrue()
            ->and($variant['selected_options'])->toBe([
                'Size' => 'Large',
                'Color' => 'Black',
            ])
            ->and(collect($data['variants'])->pluck('id')->all())->not->toContain($draftVariant->id)
            ->and($optionValues)->toBe(['Large', 'Black'])
            ->and(array_key_exists('tenant_id', $variant))->toBeFalse()
            ->and(array_key_exists('cost_price_minor', $variant))->toBeFalse()
            ->and(array_key_exists('metadata', $variant))->toBeFalse();
    });

    it('falls back to product price and marks missing variant inventory unavailable', function (): void {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->for($tenant)->create(['subdomain' => 'variant-missing-inventory']);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'fallback-price-product',
            'price_minor' => 99000,
            'status' => ProductStatus::Active,
            'published_at' => now()->subMinute(),
        ]);
        $option = ProductOption::factory()->forProduct($product)->create(['name' => 'Size']);
        $value = ProductOptionValue::factory()->forOption($option)->create(['value' => 'Large']);
        $variant = ProductVariant::factory()->forProduct($product)->create([
            'sku' => 'FALLBACK-L',
            'option_signature' => 'size=large',
            'price_minor' => null,
            'status' => ProductStatus::Active,
        ]);
        ProductVariantOptionValue::factory()->forVariantAndOptionValue($variant, $value)->create();

        $response = $this->getJson("/api/storefront/{$store->subdomain}/products/{$product->slug}");

        $response->assertOk();

        $variantPayload = $response->json('data.variants.0');

        expect($variantPayload['price_minor'])->toBeNull()
            ->and($variantPayload['effective_price_minor'])->toBe(99000)
            ->and($variantPayload['available_quantity'])->toBe(0)
            ->and($variantPayload['is_available'])->toBeFalse();
    });

    it('reports available variants for backordered and untracked inventory', function (): void {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->for($tenant)->create(['subdomain' => 'variant-availability']);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'availability-product',
            'status' => ProductStatus::Active,
            'published_at' => now()->subMinute(),
        ]);
        $option = ProductOption::factory()->forProduct($product)->create(['name' => 'Mode']);
        $backorderValue = ProductOptionValue::factory()->forOption($option)->create([
            'value' => 'Backorder',
            'position' => 0,
        ]);
        $untrackedValue = ProductOptionValue::factory()->forOption($option)->create([
            'value' => 'Untracked',
            'position' => 1,
        ]);
        $backorderVariant = ProductVariant::factory()->forProduct($product)->create([
            'option_signature' => 'mode=backorder',
            'status' => ProductStatus::Active,
            'sort_order' => 0,
        ]);
        $untrackedVariant = ProductVariant::factory()->forProduct($product)->create([
            'option_signature' => 'mode=untracked',
            'status' => ProductStatus::Active,
            'sort_order' => 1,
        ]);
        ProductVariantOptionValue::factory()->forVariantAndOptionValue($backorderVariant, $backorderValue)->create();
        ProductVariantOptionValue::factory()->forVariantAndOptionValue($untrackedVariant, $untrackedValue)->create();
        InventoryItem::factory()->forProduct($product)->create([
            'product_variant_id' => $backorderVariant->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
            'track_quantity' => true,
            'allow_backorders' => true,
        ]);
        InventoryItem::factory()->forProduct($product)->create([
            'product_variant_id' => $untrackedVariant->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
            'track_quantity' => false,
            'allow_backorders' => false,
        ]);

        $response = $this->getJson("/api/storefront/{$store->subdomain}/products/{$product->slug}");

        $response->assertOk();

        $variants = collect($response->json('data.variants'))->keyBy('option_signature');

        expect($variants['mode=backorder']['available_quantity'])->toBe(0)
            ->and($variants['mode=backorder']['is_available'])->toBeTrue()
            ->and($variants['mode=untracked']['available_quantity'])->toBeNull()
            ->and($variants['mode=untracked']['is_available'])->toBeTrue();
    });

    it('keeps simple product listing responses backward compatible', function (): void {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->for($tenant)->create(['subdomain' => 'simple-listing']);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'simple-product',
            'status' => ProductStatus::Active,
            'published_at' => now()->subMinute(),
        ]);
        InventoryItem::factory()->forProduct($product)->create([
            'quantity' => 10,
            'reserved_quantity' => 2,
        ]);

        $listingResponse = $this->getJson("/api/storefront/{$store->subdomain}/products");

        $listingResponse
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.inventory.available_quantity', 8)
            ->assertJsonMissingPath('data.0.variants')
            ->assertJsonMissingPath('data.0.options');

        $detailResponse = $this->getJson("/api/storefront/{$store->subdomain}/products/{$product->slug}");

        $detailResponse
            ->assertOk()
            ->assertJsonPath('data.inventory.available_quantity', 8)
            ->assertJsonPath('data.variants', [])
            ->assertJsonPath('data.options', []);
    });
});
