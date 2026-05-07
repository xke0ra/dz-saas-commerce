<?php

namespace App\Filament\Vendor\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantPermission;
use App\Filament\Vendor\Pages\BillingOverview;
use App\Filament\Vendor\Resources\Invoices\InvoiceResource;
use App\Filament\Vendor\Resources\SubscriptionPayments\SubscriptionPaymentResource;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Support\Tenancy\CurrentTenant;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class BillingStatusWidget extends Widget
{
    protected static ?int $sort = -4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.vendor.widgets.billing-status-widget';

    public static function canView(): bool
    {
        if (! (auth()->user()?->hasCurrentTenantPermission(TenantPermission::BillingManage) ?? false)) {
            return false;
        }

        return (new self)->hasBillingAlert();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return $this->billingStatusData();
    }

    /**
     * @return array<string, mixed>
     */
    public function billingStatusData(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return $this->emptyData();
        }

        $subscription = $this->currentSubscription($tenantId);
        $overdueInvoices = $this->overdueInvoices($tenantId);
        $overdueBalanceMinor = (int) Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('status', InvoiceStatus::Overdue)
            ->where('balance_minor', '>', 0)
            ->sum('balance_minor');

        return [
            'subscription' => $subscription,
            'overdueInvoices' => $overdueInvoices,
            'overdueInvoicesCount' => $this->overdueInvoicesCount($tenantId),
            'overdueBalanceMinor' => $overdueBalanceMinor,
            'currency' => $overdueInvoices->first()?->currency ?? $subscription?->plan?->currency ?? 'DZD',
            'severity' => $this->severity($subscription, $overdueInvoices->isNotEmpty()),
            'links' => $this->links(),
        ];
    }

    public function hasBillingAlert(): bool
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return false;
        }

        $subscription = $this->currentSubscription($tenantId);

        if ($subscription !== null && in_array($subscription->status, $this->alertSubscriptionStatuses(), true)) {
            return true;
        }

        return Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('status', InvoiceStatus::Overdue)
            ->where('balance_minor', '>', 0)
            ->exists();
    }

    public function money(?int $amountMinor, string $currency = 'DZD'): string
    {
        return number_format(($amountMinor ?? 0) / 100, 2).' '.$currency;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyData(): array
    {
        return [
            'subscription' => null,
            'overdueInvoices' => collect(),
            'overdueInvoicesCount' => 0,
            'overdueBalanceMinor' => 0,
            'currency' => 'DZD',
            'severity' => 'warning',
            'links' => $this->links(),
        ];
    }

    private function currentSubscription(string $tenantId): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->first();
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function overdueInvoices(string $tenantId): Collection
    {
        return Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('status', InvoiceStatus::Overdue)
            ->where('balance_minor', '>', 0)
            ->orderBy('due_at')
            ->limit(3)
            ->get();
    }

    private function overdueInvoicesCount(string $tenantId): int
    {
        return Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('status', InvoiceStatus::Overdue)
            ->where('balance_minor', '>', 0)
            ->count();
    }

    /**
     * @return array<int, SubscriptionStatus>
     */
    private function alertSubscriptionStatuses(): array
    {
        return [
            SubscriptionStatus::PastDue,
            SubscriptionStatus::GracePeriod,
            SubscriptionStatus::Suspended,
        ];
    }

    private function severity(?Subscription $subscription, bool $hasOverdueInvoices): string
    {
        if ($subscription?->status === SubscriptionStatus::Suspended || $hasOverdueInvoices) {
            return 'danger';
        }

        return 'warning';
    }

    /**
     * @return array<string, string>
     */
    private function links(): array
    {
        return [
            'billing' => BillingOverview::getUrl(panel: 'vendor'),
            'invoices' => InvoiceResource::getUrl(panel: 'vendor'),
            'recordPayment' => SubscriptionPaymentResource::getUrl('create', panel: 'vendor'),
        ];
    }
}
