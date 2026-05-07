<?php

namespace App\Filament\Vendor\Pages;

use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantSwitcher;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class SwitchTenant extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?int $navigationSort = -10;

    protected static ?string $navigationLabel = 'Switch tenant';

    protected static ?string $title = 'Switch tenant';

    protected static ?string $slug = 'switch-tenant';

    protected string $view = 'filament.vendor.pages.switch-tenant';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isSuperAdmin() || $user->tenants()->exists();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Choose the tenant context used by vendor dashboard resources.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'availableTenants' => $user instanceof User
                ? app(TenantSwitcher::class)->availableTenantsFor($user)
                : collect(),
            'currentTenant' => app(CurrentTenant::class)->get(),
            'switchRoute' => route('vendor.tenants.switch'),
        ];
    }
}
