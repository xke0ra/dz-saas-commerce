<?php

use App\Actions\Billing\StartTenantSubscription;
use App\Enums\PlanFeatureKey;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreSetting;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Filament\Facades\Filament;

it('allows tenant owners to create products for the current tenant', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);
    startTenantPermissionPolicySubscription($tenant);

    withCurrentTenant($tenant, function () use ($user): void {
        expect($user->can('create', Product::class))->toBeTrue();
    });
});

it('does not let a role in another tenant grant create access for the current tenant', function (): void {
    $user = User::factory()->create();
    $ownedTenant = Tenant::factory()->create();
    $staffTenant = Tenant::factory()->create();

    $ownedTenant->users()->attach($user, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);
    $staffTenant->users()->attach($user, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    withCurrentTenant($staffTenant, function () use ($user): void {
        expect($user->can('create', Product::class))->toBeFalse();
    });
});

it('can grant a granular tenant permission through pivot permissions', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => json_encode([
            TenantPermission::ProductsCreate->value => true,
        ]),
    ]);
    startTenantPermissionPolicySubscription($tenant);

    withCurrentTenant($tenant, function () use ($user): void {
        expect($user->can('create', Product::class))->toBeTrue();
    });
});

it('can revoke a default role permission through pivot permissions', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreAdmin->value,
        'permissions' => json_encode([
            TenantPermission::ProductsCreate->value => false,
        ]),
    ]);

    withCurrentTenant($tenant, function () use ($user): void {
        expect($user->can('create', Product::class))->toBeFalse();
    });
});

it('does not let a vendor view a product without tenant membership', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $otherTenant->id]);

    $tenant->users()->attach($user, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);

    expect($user->can('view', $product))->toBeFalse();
});

it('protects store and theme settings with store update permission', function (): void {
    $staff = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create();
    $storeSetting = StoreSetting::factory()->forStore($store)->create();
    $themeSetting = ThemeSetting::factory()->forStore($store)->create();

    $tenant->users()->attach($staff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    withCurrentTenant($tenant, function () use ($staff, $storeSetting, $themeSetting): void {
        expect($staff->can('view', $storeSetting))->toBeTrue()
            ->and($staff->can('update', $storeSetting))->toBeFalse()
            ->and($staff->can('view', $themeSetting))->toBeTrue()
            ->and($staff->can('update', $themeSetting))->toBeFalse();
    });

    $tenant->users()->updateExistingPivot($staff->id, [
        'permissions' => json_encode([
            TenantPermission::StoresUpdate->value => true,
        ]),
    ]);

    withCurrentTenant($tenant, function () use ($staff, $storeSetting, $themeSetting): void {
        expect($staff->fresh()->can('update', $storeSetting))->toBeTrue()
            ->and($staff->fresh()->can('update', $themeSetting))->toBeTrue();
    });
});

it('protects coupons with dedicated coupon permissions', function (): void {
    $staff = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $coupon = Coupon::factory()->create(['tenant_id' => $tenant->id]);

    $tenant->users()->attach($staff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    withCurrentTenant($tenant, function () use ($staff, $coupon): void {
        expect($staff->can('viewAny', Coupon::class))->toBeFalse()
            ->and($staff->can('view', $coupon))->toBeFalse()
            ->and($staff->can('create', Coupon::class))->toBeFalse();
    });

    $tenant->users()->updateExistingPivot($staff->id, [
        'permissions' => json_encode([
            TenantPermission::CouponsView->value => true,
        ]),
    ]);

    withCurrentTenant($tenant, function () use ($staff, $coupon): void {
        expect($staff->fresh()->can('viewAny', Coupon::class))->toBeTrue()
            ->and($staff->fresh()->can('view', $coupon))->toBeTrue()
            ->and($staff->fresh()->can('create', Coupon::class))->toBeFalse();
    });

    $tenant->users()->updateExistingPivot($staff->id, [
        'permissions' => json_encode([
            TenantPermission::CouponsView->value => true,
            TenantPermission::CouponsManage->value => true,
        ]),
    ]);

    withCurrentTenant($tenant, function () use ($staff, $coupon): void {
        expect($staff->fresh()->can('create', Coupon::class))->toBeTrue()
            ->and($staff->fresh()->can('update', $coupon))->toBeTrue()
            ->and($staff->fresh()->can('delete', $coupon))->toBeTrue();
    });
});

it('allows vendor panel access when the resolved tenant grants base store access', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    expect($user->canAccessPanel(Filament::getPanel('vendor')))->toBeTrue();
});

it('denies vendor panel access when base store access is explicitly revoked', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => json_encode([
            TenantPermission::StoresView->value => false,
        ]),
    ]);

    expect($user->canAccessPanel(Filament::getPanel('vendor')))->toBeFalse();
});

it('allows tenant owners to manage staff memberships for their tenant', function (): void {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $tenant->users()->attach($owner, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);
    $tenant->users()->attach($staff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    $membership = TenantUser::query()
        ->withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $staff->id)
        ->firstOrFail();

    withCurrentTenant($tenant, function () use ($owner, $membership): void {
        expect($owner->can('viewAny', TenantUser::class))->toBeTrue()
            ->and($owner->can('update', $membership))->toBeTrue()
            ->and($owner->can('delete', $membership))->toBeTrue();
    });
});

it('does not allow store admins to manage staff unless granted explicitly', function (): void {
    $admin = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($admin, [
        'role' => TenantRole::StoreAdmin->value,
        'permissions' => null,
    ]);

    withCurrentTenant($tenant, function () use ($admin): void {
        expect($admin->can('viewAny', TenantUser::class))->toBeFalse();
    });

    $tenant->users()->updateExistingPivot($admin->id, [
        'permissions' => json_encode([
            TenantPermission::StaffManage->value => true,
        ]),
    ]);

    withCurrentTenant($tenant, function () use ($admin): void {
        expect($admin->fresh()->can('viewAny', TenantUser::class))->toBeTrue();
    });
});

it('does not allow users to delete their own staff membership', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);

    $membership = TenantUser::query()
        ->withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $owner->id)
        ->firstOrFail();

    withCurrentTenant($tenant, function () use ($owner, $membership): void {
        expect($owner->can('delete', $membership))->toBeFalse();
    });
});

function withCurrentTenant(Tenant $tenant, Closure $callback): void
{
    $currentTenant = app(CurrentTenant::class);
    $currentTenant->set($tenant);

    try {
        $callback();
    } finally {
        $currentTenant->forget();
    }
}

function startTenantPermissionPolicySubscription(Tenant $tenant): void
{
    $plan = Plan::query()->create([
        'name' => 'Policy Test',
        'slug' => 'policy-test-'.str()->random(8),
        'price_minor' => 0,
        'currency' => 'DZD',
        'billing_interval' => 'monthly',
        'is_active' => true,
        'sort_order' => 10,
        'metadata' => [],
    ]);

    foreach ([PlanFeatureKey::MaxProducts->value => 100, PlanFeatureKey::MaxStaffUsers->value => 100] as $key => $value) {
        PlanFeature::query()->create([
            'plan_id' => $plan->id,
            'key' => $key,
            'value' => ['value' => $value],
        ]);
    }

    app(StartTenantSubscription::class)->handle($tenant, $plan, createInvoice: false);
}
