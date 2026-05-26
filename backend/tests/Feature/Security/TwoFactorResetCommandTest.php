<?php

use App\Filament\Pages\TwoFactorAuthenticationPage;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Auth\PanelAppAuthentication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Command\Command;

it('refuses to reset two factor authentication without confirm', function (): void {
    $user = twoFactorResetCommandUser();

    $this->artisan('security:reset-two-factor', [
        '--user-id' => $user->id,
        '--reason' => 'verified identity by support runbook',
    ])->assertExitCode(Command::INVALID);

    expect($user->fresh()->hasTwoFactorAuthenticationEnabled())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'two_factor_reset_by_operator')->exists())->toBeFalse();
});

it('refuses to reset two factor authentication without a reason', function (): void {
    $user = twoFactorResetCommandUser();

    $this->artisan('security:reset-two-factor', [
        '--user-id' => $user->id,
        '--confirm' => true,
    ])->assertExitCode(Command::INVALID);

    expect($user->fresh()->hasTwoFactorAuthenticationEnabled())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'two_factor_reset_by_operator')->exists())->toBeFalse();
});

it('does not clear two factor authentication in dry run mode', function (): void {
    $user = twoFactorResetCommandUser();

    $this->artisan('security:reset-two-factor', [
        '--email' => $user->email,
        '--reason' => 'verified identity by support runbook',
        '--dry-run' => true,
    ])->assertExitCode(Command::SUCCESS);

    expect($user->fresh()->hasTwoFactorAuthenticationEnabled())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'two_factor_reset_by_operator')->exists())->toBeFalse();
});

it('still requires a reason for dry run mode', function (): void {
    $user = twoFactorResetCommandUser();

    $this->artisan('security:reset-two-factor', [
        '--email' => $user->email,
        '--dry-run' => true,
    ])->assertExitCode(Command::INVALID);

    expect($user->fresh()->hasTwoFactorAuthenticationEnabled())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'two_factor_reset_by_operator')->exists())->toBeFalse();
});

it('treats users without enabled two factor authentication as a safe no-op', function (): void {
    $user = User::factory()->superAdmin()->create();

    $this->artisan('security:reset-two-factor', [
        '--user-id' => $user->id,
        '--reason' => 'verified identity by support runbook',
        '--confirm' => true,
    ])->assertExitCode(Command::SUCCESS);

    expect($user->fresh()->hasTwoFactorAuthenticationEnabled())->toBeFalse()
        ->and($user->fresh()->two_factor_disabled_at)->toBeNull()
        ->and(AuditLog::query()->where('event', 'two_factor_reset_by_operator')->exists())->toBeFalse();
});

it('clears two factor fields and records disabled timestamp during reset', function (): void {
    $user = twoFactorResetCommandUser();
    $rawUser = DB::table('users')->where('id', $user->id)->first();

    expect($rawUser->two_factor_secret)->not->toBeNull()
        ->and($rawUser->two_factor_recovery_codes)->not->toBeNull();

    $this->artisan('security:reset-two-factor', [
        '--user-id' => $user->id,
        '--reason' => 'verified identity by support runbook',
        '--confirm' => true,
    ])->assertExitCode(Command::SUCCESS);

    $user = $user->fresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->two_factor_enabled_at)->toBeNull()
        ->and($user->two_factor_last_challenged_at)->toBeNull()
        ->and($user->two_factor_disabled_at)->not->toBeNull();
});

it('records reset audit without raw secret or recovery code', function (): void {
    $recoveryCode = 'reset-recovery-code';
    $user = twoFactorResetCommandUser(recoveryCodes: [$recoveryCode]);
    $secret = $user->two_factor_secret;

    $this->artisan('security:reset-two-factor', [
        '--email' => $user->email,
        '--reason' => 'verified identity by support runbook',
        '--confirm' => true,
    ])->assertExitCode(Command::SUCCESS);

    $auditLog = AuditLog::query()
        ->where('event', 'two_factor_reset_by_operator')
        ->firstOrFail();
    $auditPayload = json_encode([
        'metadata' => $auditLog->metadata,
        'old_values' => $auditLog->old_values,
        'new_values' => $auditLog->new_values,
    ]) ?: '';

    expect($auditLog->auditable_type)->toBe($user->getMorphClass())
        ->and($auditLog->auditable_id)->toBe((string) $user->id)
        ->and($auditLog->metadata)->toMatchArray([
            'target_user_id' => $user->id,
            'target_email' => maskTwoFactorResetCommandEmail($user->email),
            'actor_user_id' => null,
            'reason' => 'verified identity by support runbook',
            'source' => 'artisan',
        ])
        ->and($auditPayload)->not->toContain($user->email)
        ->and($auditPayload)->not->toContain($secret)
        ->and($auditPayload)->not->toContain($recoveryCode);
});

it('records the actor when actor id is provided', function (): void {
    $target = twoFactorResetCommandUser();
    $actor = User::factory()->superAdmin()->create();

    $this->artisan('security:reset-two-factor', [
        '--user-id' => $target->id,
        '--reason' => 'verified identity by support runbook',
        '--actor-id' => $actor->id,
        '--confirm' => true,
    ])->assertExitCode(Command::SUCCESS);

    $auditLog = AuditLog::query()
        ->where('event', 'two_factor_reset_by_operator')
        ->firstOrFail();

    expect($auditLog->actor_id)->toBe($actor->id)
        ->and($auditLog->metadata)->toMatchArray([
            'actor_user_id' => $actor->id,
        ]);
});

it('fails safely for an unknown target user', function (): void {
    $this->artisan('security:reset-two-factor', [
        '--email' => 'missing@example.test',
        '--reason' => 'verified identity by support runbook',
        '--confirm' => true,
    ])->assertExitCode(Command::FAILURE);

    expect(AuditLog::query()->where('event', 'two_factor_reset_by_operator')->exists())->toBeFalse();
});

it('redirects required users back to setup after reset', function (): void {
    $user = twoFactorResetCommandUser(User::factory()->superAdmin()->create());

    $this->artisan('security:reset-two-factor', [
        '--user-id' => $user->id,
        '--reason' => 'verified identity by support runbook',
        '--confirm' => true,
    ])->assertExitCode(Command::SUCCESS);

    $this->actingAs($user->fresh())
        ->get('/admin')
        ->assertRedirect(TwoFactorAuthenticationPage::getUrl(panel: 'admin'));
});

/**
 * @param  array<string>  $recoveryCodes
 */
function twoFactorResetCommandUser(?User $user = null, array $recoveryCodes = ['reset-code']): User
{
    $user ??= User::factory()->superAdmin()->create();
    $secret = app(PanelAppAuthentication::class)->generateSecret();

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $recoveryCodes),
        'two_factor_confirmed_at' => now()->subMinute(),
        'two_factor_enabled_at' => now()->subMinute(),
        'two_factor_last_challenged_at' => now()->subMinute(),
    ])->save();

    return $user->fresh();
}

function maskTwoFactorResetCommandEmail(string $email): string
{
    [$localPart, $domain] = explode('@', $email, 2);

    return (substr($localPart, 0, 1) ?: '*').'***@'.$domain;
}
