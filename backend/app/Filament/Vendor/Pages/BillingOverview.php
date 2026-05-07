<?php

namespace App\Filament\Vendor\Pages;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\TenantPermission;
use App\Filament\Vendor\Resources\Invoices\InvoiceResource;
use App\Filament\Vendor\Resources\SubscriptionPayments\SubscriptionPaymentResource;
use App\Filament\Vendor\Resources\Subscriptions\SubscriptionResource;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Support\Tenancy\CurrentTenant;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

class BillingOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Billing overview';

    protected static ?string $title = 'Billing overview';

    protected static ?string $slug = 'billing';

    protected string $view = 'filament.vendor.pages.billing-overview';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasCurrentTenantPermission(TenantPermission::BillingManage) ?? false;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Subscription status, open invoices, and manual payment tracking.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return $this->billingOverviewData();
    }

    /**
     * @return array<string, mixed>
     */
    public function billingOverviewData(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return [
                'subscription' => null,
                'latestOpenInvoice' => null,
                'openInvoices' => collect(),
                'recentPayments' => collect(),
                'outstandingBalanceMinor' => 0,
                'pendingPaymentsMinor' => 0,
                'currency' => 'DZD',
                'links' => $this->links(),
            ];
        }

        $subscription = Subscription::query()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->first();

        $openInvoices = Invoice::query()
            ->with('subscription.plan')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $this->openInvoiceStatuses())
            ->where('balance_minor', '>', 0)
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        $latestOpenInvoice = $openInvoices->first();
        $currency = $subscription?->plan->currency
            ?? $latestOpenInvoice?->currency
            ?? 'DZD';

        return [
            'subscription' => $subscription,
            'latestOpenInvoice' => $latestOpenInvoice,
            'openInvoices' => $openInvoices,
            'recentPayments' => $this->recentPayments($tenantId),
            'outstandingBalanceMinor' => $this->outstandingBalanceMinor($tenantId),
            'pendingPaymentsMinor' => $this->pendingPaymentsMinor($tenantId),
            'currency' => $currency,
            'links' => $this->links(),
        ];
    }

    public function money(?int $amountMinor, string $currency = 'DZD'): string
    {
        return number_format(($amountMinor ?? 0) / 100, 2).' '.$currency;
    }

    /**
     * @return array<int, InvoiceStatus>
     */
    private function openInvoiceStatuses(): array
    {
        return [
            InvoiceStatus::Issued,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Overdue,
        ];
    }

    /**
     * @return Collection<int, SubscriptionPayment>
     */
    private function recentPayments(string $tenantId): Collection
    {
        return SubscriptionPayment::query()
            ->with('invoice')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(5)
            ->get();
    }

    private function outstandingBalanceMinor(string $tenantId): int
    {
        return (int) Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $this->openInvoiceStatuses())
            ->sum('balance_minor');
    }

    private function pendingPaymentsMinor(string $tenantId): int
    {
        return (int) SubscriptionPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', SubscriptionPaymentStatus::Pending)
            ->sum('amount_minor');
    }

    /**
     * @return array<string, string>
     */
    private function links(): array
    {
        return [
            'subscriptions' => SubscriptionResource::getUrl(panel: 'vendor'),
            'invoices' => InvoiceResource::getUrl(panel: 'vendor'),
            'payments' => SubscriptionPaymentResource::getUrl(panel: 'vendor'),
            'recordPayment' => SubscriptionPaymentResource::getUrl('create', panel: 'vendor'),
        ];
    }
}
