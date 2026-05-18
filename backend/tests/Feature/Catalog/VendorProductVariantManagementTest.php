<?php

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Filament\Vendor\Resources\ProductOptions\ProductOptionResource;
use App\Filament\Vendor\Resources\ProductOptionValues\ProductOptionValueResource;
use App\Filament\Vendor\Resources\ProductVariantOptionValues\ProductVariantOptionValueResource;
use App\Filament\Vendor\Resources\ProductVariantOptionValues\Schemas\ProductVariantOptionValueForm;
use App\Filament\Vendor\Resources\ProductVariants\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantOptionValue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Catalog\ProductVariantOptionValueValidator;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Validation\ValidationException;

it('registers vendor product variant management resource urls', function (): void {
    expect(ProductOptionResource::getUrl(panel: 'vendor'))->toContain('/vendor/product-options')
        ->and(ProductOptionValueResource::getUrl(panel: 'vendor'))->toContain('/vendor/product-option-values')
        ->and(ProductVariantResource::getUrl(panel: 'vendor'))->toContain('/vendor/product-variants')
        ->and(ProductVariantOptionValueResource::getUrl(panel: 'vendor'))->toContain('/vendor/product-variant-option-values');
});

it('scopes vendor product variant management resources to the current tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $option = ProductOption::factory()->forProduct($product)->create();
    $value = ProductOptionValue::factory()->forOption($option)->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $pivot = ProductVariantOptionValue::factory()
        ->forVariantAndOptionValue($variant, $value)
        ->create();

    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherOption = ProductOption::factory()->forProduct($otherProduct)->create();
    $otherValue = ProductOptionValue::factory()->forOption($otherOption)->create();
    $otherVariant = ProductVariant::factory()->forProduct($otherProduct)->create();
    ProductVariantOptionValue::factory()
        ->forVariantAndOptionValue($otherVariant, $otherValue)
        ->create();

    withVendorProductVariantManagementTenant($tenant, function () use ($option, $value, $variant, $pivot): void {
        expect(ProductOptionResource::getEloquentQuery()->pluck('id')->all())->toBe([$option->id])
            ->and(ProductOptionValueResource::getEloquentQuery()->pluck('id')->all())->toBe([$value->id])
            ->and(ProductVariantResource::getEloquentQuery()->pluck('id')->all())->toBe([$variant->id])
            ->and(ProductVariantOptionValueResource::getEloquentQuery()->pluck('id')->all())->toBe([$pivot->id]);
    });
});

it('protects vendor variant management resources with product permissions', function (): void {
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $option = ProductOption::factory()->forProduct($product)->create();
    $value = ProductOptionValue::factory()->forOption($option)->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $pivot = ProductVariantOptionValue::factory()
        ->forVariantAndOptionValue($variant, $value)
        ->create();

    $tenant->users()->attach($viewer, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);
    $tenant->users()->attach($blocked, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => json_encode([
            TenantPermission::ProductsView->value => false,
        ]),
    ]);

    withVendorProductVariantManagementTenant($tenant, function () use ($viewer, $blocked, $option, $value, $variant, $pivot): void {
        expect($viewer->can('viewAny', ProductOption::class))->toBeTrue()
            ->and($viewer->can('view', $option))->toBeTrue()
            ->and($viewer->can('viewAny', ProductOptionValue::class))->toBeTrue()
            ->and($viewer->can('view', $value))->toBeTrue()
            ->and($viewer->can('viewAny', ProductVariant::class))->toBeTrue()
            ->and($viewer->can('view', $variant))->toBeTrue()
            ->and($viewer->can('viewAny', ProductVariantOptionValue::class))->toBeTrue()
            ->and($viewer->can('view', $pivot))->toBeTrue()
            ->and($blocked->can('viewAny', ProductOption::class))->toBeFalse()
            ->and($blocked->can('viewAny', ProductOptionValue::class))->toBeFalse()
            ->and($blocked->can('viewAny', ProductVariant::class))->toBeFalse()
            ->and($blocked->can('viewAny', ProductVariantOptionValue::class))->toBeFalse();
    });
});

it('allows product managers to create and update variant management records', function (): void {
    $manager = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $tenant->users()->attach($manager, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => json_encode([
            TenantPermission::ProductsCreate->value => true,
            TenantPermission::ProductsUpdate->value => true,
        ]),
    ]);

    withVendorProductVariantManagementTenant($tenant, function () use ($manager, $product): void {
        expect($manager->can('create', ProductOption::class))->toBeTrue()
            ->and($manager->can('create', ProductOptionValue::class))->toBeTrue()
            ->and($manager->can('create', ProductVariant::class))->toBeTrue()
            ->and($manager->can('create', ProductVariantOptionValue::class))->toBeTrue();

        $option = ProductOption::factory()->forProduct($product)->create(['name' => 'Color']);
        $value = ProductOptionValue::factory()->forOption($option)->create(['value' => 'Black']);
        $variant = ProductVariant::factory()->forProduct($product)->create([
            'option_signature' => 'color=black',
            'title' => 'Black',
        ]);
        $pivot = ProductVariantOptionValue::query()->create([
            'tenant_id' => $variant->tenant_id,
            'product_variant_id' => $variant->id,
            'product_option_value_id' => $value->id,
        ]);

        expect($manager->can('update', $option))->toBeTrue()
            ->and($manager->can('update', $value))->toBeTrue()
            ->and($manager->can('update', $variant))->toBeTrue()
            ->and($manager->can('update', $pivot))->toBeTrue();

        $option->update(['position' => 1]);
        $value->update(['position' => 1]);
        $variant->update(['sort_order' => 1]);

        expect($option->refresh()->position)->toBe(1)
            ->and($value->refresh()->position)->toBe(1)
            ->and($variant->refresh()->sort_order)->toBe(1);
    });
});

it('does not grant variant management create update or delete without product permissions', function (): void {
    $staff = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $option = ProductOption::factory()->forProduct($product)->create();
    $value = ProductOptionValue::factory()->forOption($option)->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $pivot = ProductVariantOptionValue::factory()
        ->forVariantAndOptionValue($variant, $value)
        ->create();

    $tenant->users()->attach($staff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    withVendorProductVariantManagementTenant($tenant, function () use ($staff, $option, $value, $variant, $pivot): void {
        expect($staff->can('create', ProductOption::class))->toBeFalse()
            ->and($staff->can('create', ProductOptionValue::class))->toBeFalse()
            ->and($staff->can('create', ProductVariant::class))->toBeFalse()
            ->and($staff->can('create', ProductVariantOptionValue::class))->toBeFalse()
            ->and($staff->can('update', $option))->toBeFalse()
            ->and($staff->can('update', $value))->toBeFalse()
            ->and($staff->can('update', $variant))->toBeFalse()
            ->and($staff->can('update', $pivot))->toBeFalse()
            ->and($staff->can('delete', $option))->toBeFalse()
            ->and($staff->can('delete', $value))->toBeFalse()
            ->and($staff->can('delete', $variant))->toBeFalse()
            ->and($staff->can('delete', $pivot))->toBeFalse();
    });
});

it('accepts linking a product variant to an option value from the same product and tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $option = ProductOption::factory()->forProduct($product)->create(['name' => 'Size']);
    $value = ProductOptionValue::factory()->forOption($option)->create(['value' => 'Large']);
    $variant = ProductVariant::factory()->forProduct($product)->create([
        'option_signature' => 'size=large',
    ]);

    withVendorProductVariantManagementTenant($tenant, function () use ($tenant, $variant, $value): void {
        app(ProductVariantOptionValueValidator::class)->validate($tenant->id, $variant->id, $value->id);

        $pivot = ProductVariantOptionValue::query()->create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $variant->id,
            'product_option_value_id' => $value->id,
        ]);

        expect($pivot->variant->is($variant))->toBeTrue()
            ->and($pivot->optionValue->is($value))->toBeTrue();
    });
});

it('rejects linking a product variant to an option value from another product in the same tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $variantProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
    $valueProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->forProduct($variantProduct)->create([
        'option_signature' => 'size=large',
    ]);
    $otherProductOption = ProductOption::factory()->forProduct($valueProduct)->create(['name' => 'Color']);
    $otherProductValue = ProductOptionValue::factory()->forOption($otherProductOption)->create(['value' => 'Black']);

    withVendorProductVariantManagementTenant($tenant, function () use ($tenant, $variant, $otherProductValue): void {
        app(ProductVariantOptionValueValidator::class)->validate($tenant->id, $variant->id, $otherProductValue->id);
    });
})->throws(ValidationException::class, 'The selected option value must belong to the same product as the variant.');

it('rejects updating a product variant option value to a value from another product in the same tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $variantProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
    $otherProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
    $option = ProductOption::factory()->forProduct($variantProduct)->create(['name' => 'Size']);
    $value = ProductOptionValue::factory()->forOption($option)->create(['value' => 'Large']);
    $variant = ProductVariant::factory()->forProduct($variantProduct)->create([
        'option_signature' => 'size=large',
    ]);
    $pivot = ProductVariantOptionValue::factory()
        ->forVariantAndOptionValue($variant, $value)
        ->create();
    $otherOption = ProductOption::factory()->forProduct($otherProduct)->create(['name' => 'Color']);
    $otherValue = ProductOptionValue::factory()->forOption($otherOption)->create(['value' => 'Black']);

    withVendorProductVariantManagementTenant($tenant, function () use ($tenant, $pivot, $variant, $otherValue): void {
        app(ProductVariantOptionValueValidator::class)->validate($tenant->id, $variant->id, $otherValue->id);

        $pivot->update(['product_option_value_id' => $otherValue->id]);
    });
})->throws(ValidationException::class, 'The selected option value must belong to the same product as the variant.');

it('rejects linking a product variant to an option value from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->forProduct($product)->create([
        'option_signature' => 'size=large',
    ]);
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherOption = ProductOption::factory()->forProduct($otherProduct)->create(['name' => 'Size']);
    $otherValue = ProductOptionValue::factory()->forOption($otherOption)->create(['value' => 'Large']);

    withVendorProductVariantManagementTenant($tenant, function () use ($tenant, $variant, $otherValue): void {
        app(ProductVariantOptionValueValidator::class)->validate($tenant->id, $variant->id, $otherValue->id);
    });
})->throws(ValidationException::class, 'The selected option value is not available for this tenant.');

it('filters product variant option value form options to the selected variant product and current tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $otherTenantProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $sameTenantOtherProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->forProduct($product)->create([
        'option_signature' => 'size=large',
    ]);

    $option = ProductOption::factory()->forProduct($product)->create(['name' => 'Size']);
    $value = ProductOptionValue::factory()->forOption($option)->create(['value' => 'Large']);
    $sameTenantOtherOption = ProductOption::factory()->forProduct($sameTenantOtherProduct)->create(['name' => 'Color']);
    $sameTenantOtherValue = ProductOptionValue::factory()->forOption($sameTenantOtherOption)->create(['value' => 'Black']);
    $otherTenantOption = ProductOption::factory()->forProduct($otherTenantProduct)->create(['name' => 'Size']);
    $otherTenantValue = ProductOptionValue::factory()->forOption($otherTenantOption)->create(['value' => 'Large']);

    withVendorProductVariantManagementTenant($tenant, function () use ($variant, $value, $sameTenantOtherValue, $otherTenantValue): void {
        $options = ProductVariantOptionValueForm::optionValueOptions($variant->id);

        expect(array_keys($options))->toBe([$value->id])
            ->and($options)->toHaveKey($value->id)
            ->and($options)->not->toHaveKey($sameTenantOtherValue->id)
            ->and($options)->not->toHaveKey($otherTenantValue->id);
    });
});

function withVendorProductVariantManagementTenant(Tenant $tenant, Closure $callback): void
{
    $currentTenant = app(CurrentTenant::class);
    $currentTenant->set($tenant);

    try {
        $callback();
    } finally {
        $currentTenant->forget();
    }
}
