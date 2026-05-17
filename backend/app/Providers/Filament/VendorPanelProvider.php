<?php

namespace App\Providers\Filament;

use App\Filament\Pages\TwoFactorAuthenticationPage;
use App\Filament\Pages\TwoFactorChallengePage;
use App\Http\Middleware\EnsurePanelTwoFactor;
use App\Http\Middleware\ResolveTenantFromRequest;
use App\Support\Auth\PanelAppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class VendorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('vendor')
            ->path('vendor')
            ->login()
            ->multiFactorAuthentication(PanelAppAuthentication::make()->recoverable())
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Vendor/Resources'), for: 'App\Filament\Vendor\Resources')
            ->discoverPages(in: app_path('Filament/Vendor/Pages'), for: 'App\Filament\Vendor\Pages')
            ->pages([
                Dashboard::class,
                TwoFactorAuthenticationPage::class,
                TwoFactorChallengePage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Vendor/Widgets'), for: 'App\Filament\Vendor\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                ResolveTenantFromRequest::class,
                EnsurePanelTwoFactor::class,
            ]);
    }
}
