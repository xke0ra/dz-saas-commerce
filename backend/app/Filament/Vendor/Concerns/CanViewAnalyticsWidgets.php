<?php

namespace App\Filament\Vendor\Concerns;

use App\Enums\PlanFeatureKey;
use App\Enums\TenantPermission;
use App\Support\Billing\SubscriptionFeatureGate;
use App\Support\Tenancy\CurrentTenant;

trait CanViewAnalyticsWidgets
{
    public static function canView(): bool
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return false;
        }

        if (! (auth()->user()?->hasCurrentTenantPermission(TenantPermission::AnalyticsView) ?? false)) {
            return false;
        }

        return app(SubscriptionFeatureGate::class)->enabled($tenantId, PlanFeatureKey::AdvancedAnalytics);
    }
}
