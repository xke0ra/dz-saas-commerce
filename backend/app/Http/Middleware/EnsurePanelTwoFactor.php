<?php

namespace App\Http\Middleware;

use App\Filament\Pages\TwoFactorAuthenticationPage;
use App\Filament\Pages\TwoFactorChallengePage;
use App\Models\User;
use App\Support\Auth\TwoFactorAuthentication;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelTwoFactor
{
    public function __construct(
        private readonly TwoFactorAuthentication $twoFactor,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $this->isLogoutRoute($request) || $this->isChallengeRoute($request)) {
            return $next($request);
        }

        $panelId = Filament::getCurrentPanel()?->getId();
        $isSetupRoute = $this->isSetupRoute($request);

        if ($user->hasTwoFactorAuthenticationEnabled()) {
            if (! $this->twoFactor->sessionIsConfirmed($request, $user)) {
                return redirect()->guest(TwoFactorChallengePage::getUrl(panel: $panelId));
            }

            return $next($request);
        }

        if ($user->requiresTwoFactorAuthenticationForPanel($panelId)) {
            if ($isSetupRoute) {
                return $next($request);
            }

            return redirect()->guest(TwoFactorAuthenticationPage::getUrl(panel: $panelId));
        }

        return $next($request);
    }

    private function isLogoutRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) && str_ends_with($routeName, '.auth.logout');
    }

    private function isSetupRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) && str_ends_with($routeName, '.pages.two-factor-authentication');
    }

    private function isChallengeRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) && str_ends_with($routeName, '.pages.two-factor-challenge');
    }
}
