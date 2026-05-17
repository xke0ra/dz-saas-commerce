<?php

namespace App\Support\Auth;

use App\Enums\TenantRole;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\CurrentTenant;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorAuthentication
{
    public const SESSION_CONFIRMED_AT = 'auth.2fa.confirmed_at';

    public const SESSION_USER_ID = 'auth.2fa.user_id';

    public function __construct(
        private readonly Google2FA $google2FA,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function isRequiredForPanel(User $user, ?string $panelId): bool
    {
        return match ($panelId) {
            'admin', 'support' => $user->isSuperAdmin() || $user->isPlatformSupport(),
            'vendor' => $this->isRequiredForVendorPanel($user),
            default => false,
        };
    }

    public function confirmSession(Request $request, User $user): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->put(self::SESSION_CONFIRMED_AT, now()->toISOString());
        $request->session()->put(self::SESSION_USER_ID, $user->getAuthIdentifier());
    }

    public function sessionIsConfirmed(Request $request, User $user): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        return ((string) $request->session()->get(self::SESSION_USER_ID) === (string) $user->getAuthIdentifier())
            && filled($request->session()->get(self::SESSION_CONFIRMED_AT));
    }

    public function forgetSession(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->forget([
            self::SESSION_CONFIRMED_AT,
            self::SESSION_USER_ID,
        ]);
    }

    public function verifyTotp(User $user, string $code, bool $shouldPreventCodeReuse = true): bool
    {
        $secret = $user->getAppAuthenticationSecret();

        if (blank($secret)) {
            return false;
        }

        if (! $shouldPreventCodeReuse) {
            return $this->google2FA->verifyKey($secret, $code, 8);
        }

        $cacheKey = 'app.two_factor_authentication_codes.'.md5($user->getAuthIdentifier().'|'.$secret.'|'.$code);
        $timestamp = $this->google2FA->verifyKeyNewer($secret, $code, cache()->get($cacheKey), 8);

        if ($timestamp === false) {
            return false;
        }

        if ($timestamp === true) {
            $timestamp = $this->google2FA->getTimestamp();
        }

        cache()->put($cacheKey, $timestamp, 9 * 60);

        return true;
    }

    public function verifyRecoveryCode(User $user, string $recoveryCode): bool
    {
        $remainingCodes = [];
        $isValid = false;

        foreach ($user->getAppAuthenticationRecoveryCodes() ?? [] as $hashedRecoveryCode) {
            if (Hash::check($recoveryCode, $hashedRecoveryCode)) {
                $isValid = true;

                continue;
            }

            $remainingCodes[] = $hashedRecoveryCode;
        }

        if (! $isValid) {
            return false;
        }

        $user->saveAppAuthenticationRecoveryCodes($remainingCodes);

        return true;
    }

    public function passChallenge(Request $request, User $user, string $method): void
    {
        $user->forceFill([
            'two_factor_last_challenged_at' => now(),
        ])->save();

        $this->confirmSession($request, $user);

        $this->auditLogger->record(
            event: 'two_factor_challenge_passed',
            auditable: $user,
            actor: $user,
            metadata: [
                'method' => $method,
                'panel_id' => Filament::getCurrentPanel()?->getId(),
            ],
        );
    }

    public function resetForUser(User $target, ?User $actor, string $reason, string $source = 'artisan'): void
    {
        $resetAt = now();

        $target->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_enabled_at' => null,
            'two_factor_disabled_at' => $resetAt,
            'two_factor_last_challenged_at' => null,
        ])->save();

        $this->auditLogger->record(
            event: 'two_factor_reset_by_operator',
            auditable: $target,
            actor: $actor,
            metadata: [
                'target_user_id' => $target->getKey(),
                'target_email' => $this->maskEmail($target->email),
                'actor_user_id' => $actor?->getKey(),
                'reason' => $reason,
                'source' => $source,
                'reset_at' => $resetAt->toISOString(),
            ],
        );
    }

    public function recordEnabled(User $user): void
    {
        $this->auditLogger->record(
            event: 'two_factor_enabled',
            auditable: $user,
            actor: $user,
            metadata: [
                'panel_id' => Filament::getCurrentPanel()?->getId(),
            ],
        );
    }

    public function recordDisabled(User $user): void
    {
        $this->auditLogger->record(
            event: 'two_factor_disabled',
            auditable: $user,
            actor: $user,
            metadata: [
                'panel_id' => Filament::getCurrentPanel()?->getId(),
            ],
        );
    }

    /**
     * @param  ?array<string>  $previousCodes
     * @param  ?array<string>  $newCodes
     */
    public function recordRecoveryCodesRegeneratedIfNeeded(User $user, ?array $previousCodes, ?array $newCodes): void
    {
        if ($newCodes === null || $previousCodes === null || ! $user->hasTwoFactorAuthenticationEnabled()) {
            return;
        }

        if (count($newCodes) < count($previousCodes)) {
            return;
        }

        $this->auditLogger->record(
            event: 'two_factor_recovery_codes_regenerated',
            auditable: $user,
            actor: $user,
            metadata: [
                'panel_id' => Filament::getCurrentPanel()?->getId(),
                'codes_count' => count($newCodes),
            ],
        );
    }

    private function isRequiredForVendorPanel(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = app(CurrentTenant::class)->get();

        if ($tenant === null) {
            return false;
        }

        return $user->tenantRole($tenant) === TenantRole::Owner;
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '[masked]';
        }

        [$localPart, $domain] = explode('@', $email, 2);
        $prefix = substr($localPart, 0, 1) ?: '*';

        return $prefix.'***@'.$domain;
    }
}
