<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Auth\TwoFactorAuthentication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class TwoFactorAuthenticationPage extends Page
{
    protected static ?string $slug = 'two-factor-authentication';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Two-factor auth';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Two-factor authentication';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Use an authenticator app and recovery codes to protect access to this panel.';
    }

    public function getDefaultActionSuccessRedirectUrl(Action $action): ?string
    {
        if ($action->getName() !== 'setUpAppAuthentication') {
            return parent::getDefaultActionSuccessRedirectUrl($action);
        }

        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->hasTwoFactorAuthenticationEnabled()) {
            return null;
        }

        return app(TwoFactorAuthentication::class)->pullIntendedPanelUrl(
            request(),
            Filament::getCurrentPanel()?->getId(),
            Filament::getUrl(),
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getMultiFactorAuthenticationContentComponent(),
            ]);
    }

    public function getMultiFactorAuthenticationContentComponent(): Component
    {
        $user = Filament::auth()->user();

        return Section::make('Authenticator app')
            ->compact()
            ->divided()
            ->secondary()
            ->schema(collect(Filament::getMultiFactorAuthenticationProviders())
                ->sort(fn (MultiFactorAuthenticationProvider $provider): int => $provider->isEnabled($user) ? 0 : 1)
                ->map(fn (MultiFactorAuthenticationProvider $provider): Component => Group::make($provider->getManagementSchemaComponents())
                    ->statePath($provider->getId()))
                ->all());
    }
}
