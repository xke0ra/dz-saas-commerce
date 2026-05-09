<?php

use App\Enums\PlatformRole;
use App\Enums\StoreStatus;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Support\Str;

it('can create a tenant', function (): void {
    $owner = User::factory()->create();

    $tenant = Tenant::query()->create([
        'name' => 'Demo Merchant',
        'slug' => 'demo-merchant',
        'status' => TenantStatus::Trial,
        'owner_id' => $owner->id,
        'settings' => ['locale' => 'ar'],
    ]);

    expect($tenant)
        ->exists->toBeTrue()
        ->status->toBe(TenantStatus::Trial)
        ->and($tenant->owner->is($owner))->toBeTrue()
        ->and($tenant->users()->whereKey($owner->id)->wherePivot('role', TenantRole::Owner->value)->exists())->toBeTrue();
});

it('creates stores that belong to tenants', function (): void {
    $tenant = Tenant::factory()->create();

    $store = Store::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Alger Shop',
        'slug' => 'alger-shop',
        'subdomain' => 'alger-shop-'.Str::lower(Str::random(6)),
        'status' => StoreStatus::Active,
        'locale' => 'ar',
        'currency' => 'DZD',
        'settings' => [],
    ]);

    expect($store->tenant->is($tenant))->toBeTrue()
        ->and($tenant->stores()->whereKey($store->id)->exists())->toBeTrue();
});

it('prevents a vendor from viewing another tenant store', function (): void {
    $vendor = User::factory()->create();
    $ownedTenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $otherStore = Store::factory()->for($otherTenant)->create();

    $ownedTenant->users()->attach($vendor, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);

    expect($vendor->can('view', $otherStore))->toBeFalse();
});

it('filters stores by explicit tenant scope and fails closed without tenant context', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create();
    Store::factory()->for($otherTenant)->create();

    expect(Store::query()->forTenant($tenant)->pluck('id')->all())->toBe([$store->id])
        ->and(Store::query()->forTenant($tenant->id)->pluck('id')->all())->toBe([$store->id])
        ->and(Store::query()->forTenant(null)->pluck('id')->all())->toBe([]);
});

it('allows a super admin to see tenants', function (): void {
    $admin = User::factory()->create([
        'platform_role' => PlatformRole::SuperAdmin,
    ]);
    $tenant = Tenant::factory()->create();

    expect($admin->can('viewAny', Tenant::class))->toBeTrue()
        ->and($admin->can('view', $tenant))->toBeTrue();
});

it('resolves a tenant from a storefront subdomain', function (): void {
    $tenant = Tenant::factory()->create();
    Store::factory()->for($tenant)->create([
        'subdomain' => 'vendor-one',
    ]);

    $resolvedTenant = app(TenantResolver::class)->resolveFromHost('vendor-one.platform.test');

    expect($resolvedTenant?->is($tenant))->toBeTrue();
});
