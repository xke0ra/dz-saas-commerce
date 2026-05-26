<?php

use App\Enums\PlatformRole;
use App\Enums\TenantRole;
use App\Filament\Pages\TwoFactorAuthenticationPage;
use App\Filament\Pages\TwoFactorChallengePage;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\PanelAppAuthentication;
use App\Support\Auth\TwoFactorAuthentication;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('adds encrypted two factor fields to users and hides secrets from serialization', function (): void {
    expect(Schema::hasColumns('users', [
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'two_factor_enabled_at',
        'two_factor_disabled_at',
        'two_factor_last_challenged_at',
    ]))->toBeTrue();

    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => 'SECRET-FOR-TEST',
        'two_factor_recovery_codes' => ['hashed-recovery-code'],
    ])->save();

    $rawUser = DB::table('users')->where('id', $user->id)->first();

    expect($rawUser->two_factor_secret)->not->toBe('SECRET-FOR-TEST')
        ->and($rawUser->two_factor_recovery_codes)->not->toContain('hashed-recovery-code')
        ->and($user->fresh()->two_factor_secret)->toBe('SECRET-FOR-TEST')
        ->and($user->fresh()->two_factor_recovery_codes)->toBe(['hashed-recovery-code'])
        ->and($user->fresh()->toArray())->not->toHaveKeys([
            'two_factor_secret',
            'two_factor_recovery_codes',
        ])
        ->and($user->fresh()->toJson())->not->toContain('SECRET-FOR-TEST')
        ->and($user->fresh()->toJson())->not->toContain('hashed-recovery-code');
});

it('only marks two factor authentication enabled after the secret is confirmed', function (): void {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => 'UNCONFIRMED-SECRET',
        'two_factor_confirmed_at' => null,
    ])->save();

    expect($user->fresh()->hasTwoFactorAuthenticationEnabled())->toBeFalse();

    $user->fresh()->saveAppAuthenticationSecret('CONFIRMED-SECRET');

    expect($user->fresh()->hasTwoFactorAuthenticationEnabled())->toBeTrue()
        ->and($user->fresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->fresh()->two_factor_enabled_at)->not->toBeNull();
});

it('requires setup for admin and support panels but leaves public routes unaffected', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $support = User::factory()->platformSupport()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertRedirect(TwoFactorAuthenticationPage::getUrl(panel: 'admin'));

    $this->actingAs($admin)
        ->get('/admin/two-factor-authentication')
        ->assertOk();

    $this->actingAs($support)
        ->get('/support')
        ->assertRedirect(TwoFactorAuthenticationPage::getUrl(panel: 'support'));

    $this->getJson('/api/system/health/live')->assertOk();
    $this->getJson('/api/storefront/geography/wilayas')->assertOk();
});

it('requires tenant owners in the vendor panel while vendor staff remain optional', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $staff = User::factory()->create();

    $tenant->users()->attach($owner, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);
    $tenant->users()->attach($staff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    $this->actingAs($owner)
        ->get('/vendor?tenant_id='.$tenant->id)
        ->assertRedirect(TwoFactorAuthenticationPage::getUrl(panel: 'vendor'));

    $this->actingAs($staff)
        ->get('/vendor?tenant_id='.$tenant->id)
        ->assertOk();
});

it('challenges users with enabled two factor authentication until this session passes', function (): void {
    $user = twoFactorUser(User::factory()->superAdmin()->create());

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(TwoFactorChallengePage::getUrl(panel: 'admin'));

    $this->withSession([
        TwoFactorAuthentication::SESSION_USER_ID => $user->id,
        TwoFactorAuthentication::SESSION_CONFIRMED_AT => now()->toISOString(),
    ])->actingAs($user)
        ->get('/admin')
        ->assertOk();
});

it('completes required admin two factor setup and confirms the current session', function (): void {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(TwoFactorAuthenticationPage::getUrl(panel: 'admin'))
        ->assertSessionHas('url.intended', adminPanelUrl());

    $component = Livewire::actingAs($user)
        ->test(TwoFactorAuthenticationPage::class)
        ->mountAction(TestAction::make('setUpAppAuthentication')->schemaComponent());

    $encryptedArguments = $component->instance()->mountedActions[0]['arguments']['encrypted'] ?? null;

    expect($encryptedArguments)->toBeString();

    $secret = decrypt($encryptedArguments)['secret'];

    $component
        ->setActionData([
            'code' => app(PanelAppAuthentication::class)->getCurrentCode($user, $secret),
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertRedirect(adminPanelUrl())
        ->assertSessionHas(TwoFactorAuthentication::SESSION_USER_ID, $user->id)
        ->assertSessionHas(TwoFactorAuthentication::SESSION_CONFIRMED_AT);

    $user = $user->fresh();

    expect($user->two_factor_secret)->toBe($secret)
        ->and($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->two_factor_enabled_at)->not->toBeNull()
        ->and($user->getAppAuthenticationRecoveryCodes())->toBeArray();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});

it('passes enabled admin two factor challenge with totp and rejects invalid codes', function (): void {
    $user = twoFactorUser(User::factory()->superAdmin()->create());

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(TwoFactorChallengePage::getUrl(panel: 'admin'))
        ->assertSessionHas('url.intended', adminPanelUrl());

    Livewire::actingAs($user)
        ->test(TwoFactorChallengePage::class)
        ->set('code', '000000')
        ->call('submit')
        ->assertHasErrors(['code']);

    Livewire::actingAs($user)
        ->test(TwoFactorChallengePage::class)
        ->set('code', app(PanelAppAuthentication::class)->getCurrentCode($user))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(adminPanelUrl())
        ->assertSessionHas(TwoFactorAuthentication::SESSION_USER_ID, $user->id)
        ->assertSessionHas(TwoFactorAuthentication::SESSION_CONFIRMED_AT);

    $this->actingAs($user->fresh())
        ->get('/admin')
        ->assertOk();
});

it('does not redirect back to two factor setup or challenge pages after success', function (): void {
    $user = twoFactorUser(User::factory()->superAdmin()->create());

    $this->withSession([
        'url.intended' => TwoFactorAuthenticationPage::getUrl(panel: 'admin'),
    ])->actingAs($user)
        ->get('/admin/two-factor-authentication')
        ->assertRedirect(TwoFactorChallengePage::getUrl(panel: 'admin'));

    Livewire::actingAs($user)
        ->test(TwoFactorChallengePage::class)
        ->set('code', app(PanelAppAuthentication::class)->getCurrentCode($user))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(adminPanelUrl());
});

it('passes enabled admin two factor challenge with a recovery code once', function (): void {
    $recoveryCode = 'use-this-code-once';
    $user = twoFactorUser(User::factory()->superAdmin()->create(), recoveryCodes: [$recoveryCode]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(TwoFactorChallengePage::getUrl(panel: 'admin'));

    Livewire::actingAs($user)
        ->test(TwoFactorChallengePage::class)
        ->set('useRecoveryCode', true)
        ->set('recoveryCode', $recoveryCode)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(adminPanelUrl());

    expect($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe([])
        ->and(app(TwoFactorAuthentication::class)->verifyRecoveryCode($user->fresh(), $recoveryCode))->toBeFalse();
});

it('generates filament and livewire asset urls as https behind a trusted proxy', function (): void {
    config([
        'app.asset_url' => 'https://api.mayfairs.app',
        'app.url' => 'https://api.mayfairs.app',
        'trustedproxy.proxies' => '*',
    ]);

    $response = $this->withServerVariables([
        'HTTP_HOST' => 'api.mayfairs.app',
        'HTTP_X_FORWARDED_HOST' => 'api.mayfairs.app',
        'HTTP_X_FORWARDED_PORT' => '443',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'REMOTE_ADDR' => '172.18.0.2',
    ])->get('/admin/login');

    $response->assertOk();

    expect($response->getContent())
        ->toContain('https://api.mayfairs.app')
        ->toContain('https://api.mayfairs.app/livewire')
        ->not->toContain('http://api.mayfairs.app');
});

it('verifies totp codes and rejects invalid totp codes', function (): void {
    $user = twoFactorUser();
    $appAuthentication = app(PanelAppAuthentication::class);
    $twoFactor = app(TwoFactorAuthentication::class);

    expect($twoFactor->verifyTotp($user, $appAuthentication->getCurrentCode($user), shouldPreventCodeReuse: false))->toBeTrue()
        ->and($twoFactor->verifyTotp($user, '000000', shouldPreventCodeReuse: false))->toBeFalse();
});

it('passes a challenge into the current session only', function (): void {
    $user = twoFactorUser();
    $request = Request::create('/admin/two-factor-challenge');
    $request->setLaravelSession(new Store('first-session', new ArraySessionHandler(120)));
    $twoFactor = app(TwoFactorAuthentication::class);

    expect($twoFactor->sessionIsConfirmed($request, $user))->toBeFalse();

    $twoFactor->passChallenge($request, $user, 'totp');

    expect($twoFactor->sessionIsConfirmed($request, $user))->toBeTrue()
        ->and($user->fresh()->two_factor_last_challenged_at)->not->toBeNull()
        ->and(AuditLog::query()->where('event', 'two_factor_challenge_passed')->exists())->toBeTrue();

    $otherRequest = Request::create('/admin');
    $otherRequest->setLaravelSession(new Store('second-session', new ArraySessionHandler(120)));

    expect($twoFactor->sessionIsConfirmed($otherRequest, $user))->toBeFalse();
});

it('accepts one recovery code once and consumes it', function (): void {
    $recoveryCode = 'recover-once-code';
    $user = twoFactorUser(recoveryCodes: [$recoveryCode]);
    $twoFactor = app(TwoFactorAuthentication::class);

    expect($twoFactor->verifyRecoveryCode($user, $recoveryCode))->toBeTrue()
        ->and($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe([])
        ->and($twoFactor->verifyRecoveryCode($user->fresh(), $recoveryCode))->toBeFalse();
});

it('audits enable disable and recovery code regeneration without storing raw codes', function (): void {
    $user = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
    $secret = app(PanelAppAuthentication::class)->generateSecret();

    $this->actingAs($user);
    $user->saveAppAuthenticationSecret($secret);
    $user->saveAppAuthenticationRecoveryCodes([Hash::make('first-recovery-code')]);
    $user->saveAppAuthenticationRecoveryCodes([Hash::make('second-recovery-code')]);
    $user->saveAppAuthenticationSecret(null);
    $auditMetadata = AuditLog::query()
        ->get()
        ->map(fn (AuditLog $auditLog): string => json_encode($auditLog->metadata) ?: '')
        ->implode("\n");

    expect(AuditLog::query()->where('event', 'two_factor_enabled')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'two_factor_recovery_codes_regenerated')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'two_factor_disabled')->exists())->toBeTrue()
        ->and($auditMetadata)->not->toContain('first-recovery-code')
        ->and($auditMetadata)->not->toContain('second-recovery-code')
        ->and($auditMetadata)->not->toContain($secret);
});

/**
 * @param  array<string>  $recoveryCodes
 */
function twoFactorUser(?User $user = null, array $recoveryCodes = ['recovery-code']): User
{
    $user ??= User::factory()->create();
    $secret = app(PanelAppAuthentication::class)->generateSecret();

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $recoveryCodes),
        'two_factor_confirmed_at' => now(),
        'two_factor_enabled_at' => now(),
    ])->save();

    return $user->fresh();
}

function adminPanelUrl(): string
{
    return url('/admin');
}
