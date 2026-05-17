<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Auth\TwoFactorAuthentication;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengePage extends Page
{
    protected static ?string $slug = 'two-factor-challenge';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Two-factor challenge';

    protected string $view = 'filament.pages.two-factor-challenge';

    public string $code = '';

    public string $recoveryCode = '';

    public bool $useRecoveryCode = false;

    public function mount(TwoFactorAuthentication $twoFactor): void
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->hasTwoFactorAuthenticationEnabled()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        if ($twoFactor->sessionIsConfirmed(request(), $user)) {
            redirect()->intended(Filament::getUrl());
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Enter a current authenticator code or one recovery code to continue.';
    }

    /**
     * @throws ValidationException
     */
    public function submit(TwoFactorAuthentication $twoFactor): mixed
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return redirect()->to(Filament::getLoginUrl());
        }

        $this->validate($this->useRecoveryCode ? [
            'recoveryCode' => ['required', 'string'],
        ] : [
            'code' => ['required', 'string'],
        ]);

        $rateLimitingKey = 'panel-two-factor-challenge:'.$user->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($rateLimitingKey, maxAttempts: 5)) {
            throw ValidationException::withMessages([
                $this->useRecoveryCode ? 'recoveryCode' : 'code' => 'Too many attempts. Try again later.',
            ]);
        }

        RateLimiter::hit($rateLimitingKey);

        $valid = $this->useRecoveryCode
            ? $twoFactor->verifyRecoveryCode($user, trim($this->recoveryCode))
            : $twoFactor->verifyTotp($user, preg_replace('/\s+/', '', $this->code) ?? '');

        if (! $valid) {
            throw ValidationException::withMessages([
                $this->useRecoveryCode ? 'recoveryCode' : 'code' => 'The provided two-factor code is invalid.',
            ]);
        }

        RateLimiter::clear($rateLimitingKey);

        $twoFactor->passChallenge(request(), $user, $this->useRecoveryCode ? 'recovery_code' : 'totp');

        Notification::make()
            ->title('Two-factor challenge passed')
            ->success()
            ->send();

        return redirect()->intended(Filament::getUrl());
    }
}
