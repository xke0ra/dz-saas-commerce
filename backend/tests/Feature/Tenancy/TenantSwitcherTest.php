<?php

use App\Enums\PlatformRole;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantResolver;
use App\Support\Tenancy\TenantSwitcher;
use Illuminate\Http\Request;

it('persists the selected tenant in the vendor session', function (): void {
    $user = User::factory()->create();
    $firstTenant = Tenant::factory()->create();
    $secondTenant = Tenant::factory()->create();

    attachTenantSwitcherUser($user, $firstTenant);
    attachTenantSwitcherUser($user, $secondTenant);

    $response = $this->actingAs($user)
        ->post(route('vendor.tenants.switch'), [
            'tenant_id' => $secondTenant->id,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHas(TenantSwitcher::SESSION_KEY, $secondTenant->id);
});

it('does not let a vendor switch to an unrelated tenant', function (): void {
    $user = User::factory()->create();
    $ownedTenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    attachTenantSwitcherUser($user, $ownedTenant);

    $response = $this->actingAs($user)
        ->post(route('vendor.tenants.switch'), [
            'tenant_id' => $otherTenant->id,
        ]);

    $response
        ->assertForbidden()
        ->assertSessionMissing(TenantSwitcher::SESSION_KEY);
});

it('allows a super admin to switch to any tenant', function (): void {
    $admin = User::factory()->create([
        'platform_role' => PlatformRole::SuperAdmin,
    ]);
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('vendor.tenants.switch'), [
            'tenant_id' => $tenant->id,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHas(TenantSwitcher::SESSION_KEY, $tenant->id);
});

it('resolves the vendor tenant from the persisted session', function (): void {
    $user = User::factory()->create();
    $firstTenant = Tenant::factory()->create();
    $secondTenant = Tenant::factory()->create();

    attachTenantSwitcherUser($user, $firstTenant);
    attachTenantSwitcherUser($user, $secondTenant);

    $request = Request::create('/vendor', 'GET');
    $request->setUserResolver(fn (): User => $user);

    $session = app('session.store');
    $session->put(TenantSwitcher::SESSION_KEY, $secondTenant->id);
    $request->setLaravelSession($session);

    $resolvedTenant = app(TenantResolver::class)->resolveFromRequest($request);

    expect($resolvedTenant?->is($secondTenant))->toBeTrue();
});

it('renders only available tenants on the vendor switcher page', function (): void {
    $user = User::factory()->create();
    $firstTenant = Tenant::factory()->create(['name' => 'First Tenant']);
    $secondTenant = Tenant::factory()->create(['name' => 'Second Tenant']);
    $otherTenant = Tenant::factory()->create(['name' => 'Hidden Tenant']);

    attachTenantSwitcherUser($user, $firstTenant);
    attachTenantSwitcherUser($user, $secondTenant);

    $response = $this->actingAs($user)
        ->withSession([TenantSwitcher::SESSION_KEY => $firstTenant->id])
        ->get('/vendor/switch-tenant');

    $response
        ->assertOk()
        ->assertSee($firstTenant->name)
        ->assertSee($secondTenant->name)
        ->assertDontSee($otherTenant->name);
});

function attachTenantSwitcherUser(User $user, Tenant $tenant, TenantRole $role = TenantRole::Owner): void
{
    $tenant->users()->attach($user, [
        'role' => $role->value,
        'permissions' => null,
    ]);
}
