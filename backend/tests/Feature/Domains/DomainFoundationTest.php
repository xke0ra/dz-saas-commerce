<?php

use App\Actions\Billing\StartTenantSubscription;
use App\Enums\DomainStatus;
use App\Enums\PlanFeatureKey;
use App\Enums\StoreStatus;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('creates domains with normalized hostnames and verification tokens', function (): void {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create();

    $domain = Domain::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'hostname' => 'HTTPS://Shop.Example.DZ/path',
    ]);

    expect($domain->hostname)->toBe('shop.example.dz')
        ->and($domain->verification_token)->not->toBeEmpty()
        ->and($domain->tenant->is($tenant))->toBeTrue()
        ->and($domain->store->is($store))->toBeTrue();
});

it('rejects assigning a domain to a store from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $otherStore = Store::factory()->for($otherTenant)->create();

    expect(fn () => Domain::query()
        ->withoutGlobalScope('current_tenant')
        ->create([
            'tenant_id' => $tenant->id,
            'store_id' => $otherStore->id,
            'hostname' => 'cross-tenant.example.dz',
            'status' => DomainStatus::PendingVerification,
            'verification_token' => Str::random(48),
            'metadata' => [],
        ]))->toThrow(QueryException::class);
});

it('resolves a tenant and public store from an active verified custom domain', function (): void {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    $store = Store::factory()->for($tenant)->create([
        'status' => StoreStatus::Active,
        'slug' => 'custom-domain-store',
    ]);

    Domain::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'hostname' => 'Shop.Example.DZ',
    ]);

    $resolvedTenant = app(TenantResolver::class)->resolveFromHost('shop.example.dz');

    expect($resolvedTenant?->is($tenant))->toBeTrue();

    $this->getJson('/api/storefront/resolve?host=SHOP.EXAMPLE.DZ')
        ->assertOk()
        ->assertJsonPath('data.slug', 'custom-domain-store');
});

it('does not resolve pending or disabled custom domains', function (): void {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create([
        'status' => StoreStatus::Active,
    ]);

    Domain::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'hostname' => 'pending.example.dz',
        'status' => DomainStatus::PendingVerification,
        'verified_at' => null,
    ]);
    Domain::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'hostname' => 'disabled.example.dz',
        'status' => DomainStatus::Disabled,
        'verified_at' => now(),
    ]);

    expect(app(TenantResolver::class)->resolveFromHost('pending.example.dz'))->toBeNull()
        ->and(app(TenantResolver::class)->resolveFromHost('disabled.example.dz'))->toBeNull();
});

it('falls back to the legacy store domain while the domains table is adopted', function (): void {
    $tenant = Tenant::factory()->create();
    Store::factory()->for($tenant)->create([
        'domain' => 'legacy-store.example.dz',
    ]);

    $resolvedTenant = app(TenantResolver::class)->resolveFromHost('legacy-store.example.dz');

    expect($resolvedTenant?->is($tenant))->toBeTrue();
});

it('protects domain management with tenant permissions and the custom domain feature', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create();
    $domain = Domain::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
    ]);

    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => json_encode([
            TenantPermission::DomainsView->value => true,
            TenantPermission::DomainsManage->value => true,
        ]),
    ]);
    startDomainSubscription($tenant, customDomainEnabled: false);

    withDomainTenant($tenant, function () use ($user, $domain): void {
        expect($user->can('viewAny', Domain::class))->toBeTrue()
            ->and($user->can('view', $domain))->toBeTrue()
            ->and($user->can('create', Domain::class))->toBeFalse()
            ->and($user->can('update', $domain))->toBeFalse();
    });

    startDomainSubscription($tenant, customDomainEnabled: true);

    withDomainTenant($tenant, function () use ($user, $domain): void {
        expect($user->fresh()->can('create', Domain::class))->toBeTrue()
            ->and($user->fresh()->can('update', $domain))->toBeTrue()
            ->and($user->fresh()->can('delete', $domain))->toBeTrue();
    });
});

function startDomainSubscription(Tenant $tenant, bool $customDomainEnabled): void
{
    $plan = Plan::query()->create([
        'name' => 'Domain Test',
        'slug' => 'domain-test-'.str()->random(8),
        'price_minor' => 0,
        'currency' => 'DZD',
        'billing_interval' => 'monthly',
        'is_active' => true,
        'sort_order' => 10,
        'metadata' => [],
    ]);

    PlanFeature::query()->create([
        'plan_id' => $plan->id,
        'key' => PlanFeatureKey::CustomDomain->value,
        'value' => ['value' => $customDomainEnabled],
    ]);

    app(StartTenantSubscription::class)->handle($tenant, $plan, createInvoice: false);
}

function withDomainTenant(Tenant $tenant, Closure $callback): void
{
    $currentTenant = app(CurrentTenant::class);
    $currentTenant->set($tenant);

    try {
        $callback();
    } finally {
        $currentTenant->forget();
    }
}
