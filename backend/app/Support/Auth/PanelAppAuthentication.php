<?php

namespace App\Support\Auth;

use Closure;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\Auth\Authenticatable;

class PanelAppAuthentication extends AppAuthentication
{
    /**
     * @param  Authenticatable&HasAppAuthentication&HasAppAuthenticationRecovery  $user
     * @return array<Component>
     */
    public function getChallengeFormComponents(Authenticatable $user): array
    {
        $isRecoverable = $this->isRecoverable();

        return [
            OneTimeCodeInput::make('code')
                ->label(__('filament-panels::auth/multi-factor/app/provider.login_form.code.label'))
                ->belowContent(fn (Get $get): Action => Action::make('useRecoveryCode')
                    ->label(__('filament-panels::auth/multi-factor/app/provider.login_form.code.actions.use_recovery_code.label'))
                    ->link()
                    ->action(fn (Set $set) => $set('useRecoveryCode', true))
                    ->visible(fn (): bool => $isRecoverable && (! $get('useRecoveryCode'))))
                ->validationAttribute(__('filament-panels::auth/multi-factor/app/provider.login_form.code.validation_attribute'))
                ->required(fn (Get $get): bool => (! $isRecoverable) || blank($get('recoveryCode')))
                ->rule(function () use ($user): Closure {
                    return function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                        if (is_string($value) && $this->verifyCode($value, $this->getSecret($user), shouldPreventCodeReuse: true)) {
                            app(TwoFactorAuthentication::class)->passChallenge(request(), $user, 'totp');

                            return;
                        }

                        $fail(__('filament-panels::auth/multi-factor/app/provider.login_form.code.messages.invalid'));
                    };
                }),
            TextInput::make('recoveryCode')
                ->label(__('filament-panels::auth/multi-factor/app/provider.login_form.recovery_code.label'))
                ->validationAttribute(__('filament-panels::auth/multi-factor/app/provider.login_form.recovery_code.validation_attribute'))
                ->password()
                ->revealable(filament()->arePasswordsRevealable())
                ->rule(function () use ($user): Closure {
                    return function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                        if (blank($value)) {
                            return;
                        }

                        if (is_string($value) && $this->verifyRecoveryCode($value, $user)) {
                            app(TwoFactorAuthentication::class)->passChallenge(request(), $user, 'recovery_code');

                            return;
                        }

                        $fail(__('filament-panels::auth/multi-factor/app/provider.login_form.recovery_code.messages.invalid'));
                    };
                })
                ->visible(fn (Get $get): bool => $isRecoverable && $get('useRecoveryCode'))
                ->live(onBlur: true),
        ];
    }
}
