<?php

use App\Actions\Billing\RecordSubscriptionPayment;
use App\Actions\Billing\StartTenantSubscription;
use App\Enums\InvoiceStatus;
use App\Enums\PlanFeatureKey;
use App\Enums\SubscriptionPaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Filament\Vendor\Pages\BillingOverview;
use App\Filament\Vendor\Resources\Invoices\InvoiceResource as VendorInvoiceResource;
use App\Filament\Vendor\Resources\SubscriptionPayments\SubscriptionPaymentResource as VendorSubscriptionPaymentResource;
use App\Filament\Vendor\Resources\Subscriptions\SubscriptionResource as VendorSubscriptionResource;
use App\Filament\Vendor\Widgets\BillingStatusWidget;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;

it('scopes vendor billing resources to the current tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $plan = createVendorBillingFilamentPlan();

    $subscription = app(StartTenantSubscription::class)->handle($tenant, $plan);
    $otherSubscription = app(StartTenantSubscription::class)->handle($otherTenant, $plan);

    $invoice = Invoice::query()
        ->withoutGlobalScope('current_tenant')
        ->where('subscription_id', $subscription->id)
        ->firstOrFail();
    $otherInvoice = Invoice::query()
        ->withoutGlobalScope('current_tenant')
        ->where('subscription_id', $otherSubscription->id)
        ->firstOrFail();

    $payment = app(RecordSubscriptionPayment::class)->handle(
        invoice: $invoice,
        amountMinor: 100000,
        method: SubscriptionPaymentMethod::ManualBankTransfer,
        reference: 'TENANT-PAY',
    );
    app(RecordSubscriptionPayment::class)->handle(
        invoice: $otherInvoice,
        amountMinor: 100000,
        method: SubscriptionPaymentMethod::ManualBankTransfer,
        reference: 'OTHER-PAY',
    );

    withVendorBillingTenant($tenant, function () use ($subscription, $invoice, $payment): void {
        expect(VendorSubscriptionResource::getEloquentQuery()->pluck('id')->all())->toBe([$subscription->id])
            ->and(VendorInvoiceResource::getEloquentQuery()->pluck('id')->all())->toBe([$invoice->id])
            ->and(VendorSubscriptionPaymentResource::getEloquentQuery()->pluck('id')->all())->toBe([$payment->id]);
    });
});

it('allows only tenant billing managers to create subscription payments from vendor panel', function (): void {
    $owner = User::factory()->create();
    $plainStaff = User::factory()->create();
    $billingStaff = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $tenant->users()->attach($owner, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);
    $tenant->users()->attach($plainStaff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);
    $tenant->users()->attach($billingStaff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => json_encode([
            TenantPermission::BillingManage->value => true,
        ]),
    ]);

    withVendorBillingTenant($tenant, function () use ($owner, $plainStaff, $billingStaff): void {
        expect($owner->can('create', SubscriptionPayment::class))->toBeTrue()
            ->and($billingStaff->can('create', SubscriptionPayment::class))->toBeTrue()
            ->and($plainStaff->can('create', SubscriptionPayment::class))->toBeFalse();
    });
});

it('builds tenant-scoped billing overview data for billing managers', function (): void {
    $owner = User::factory()->create();
    $plainStaff = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);
    $tenant->users()->attach($plainStaff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);
    $plan = createVendorBillingFilamentPlan();
    $subscription = app(StartTenantSubscription::class)->handle($tenant, $plan);
    $invoice = Invoice::query()
        ->withoutGlobalScope('current_tenant')
        ->where('subscription_id', $subscription->id)
        ->firstOrFail();
    $payment = app(RecordSubscriptionPayment::class)->handle(
        invoice: $invoice,
        amountMinor: 40000,
        method: SubscriptionPaymentMethod::ManualBankTransfer,
        reference: 'OVERVIEW-PAY',
    );

    $this->actingAs($owner);

    withVendorBillingTenant($tenant, function () use ($subscription, $invoice, $payment): void {
        expect(BillingOverview::canAccess())->toBeTrue();

        $data = (new BillingOverview)->billingOverviewData();

        expect($data['subscription']->id)->toBe($subscription->id)
            ->and($data['latestOpenInvoice']->id)->toBe($invoice->id)
            ->and($data['outstandingBalanceMinor'])->toBe(100000)
            ->and($data['pendingPaymentsMinor'])->toBe(40000)
            ->and($data['currency'])->toBe('DZD')
            ->and($data['openInvoices']->pluck('id')->all())->toBe([$invoice->id])
            ->and($data['recentPayments']->pluck('id')->all())->toBe([$payment->id]);
    });

    $this->actingAs($plainStaff);

    withVendorBillingTenant($tenant, function (): void {
        expect(BillingOverview::canAccess())->toBeFalse();
    });
});

it('shows vendor billing status widget only when billing attention is required', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);
    $plan = createVendorBillingFilamentPlan();
    $subscription = app(StartTenantSubscription::class)->handle($tenant, $plan);
    $invoice = Invoice::query()
        ->withoutGlobalScope('current_tenant')
        ->where('subscription_id', $subscription->id)
        ->firstOrFail();

    $this->actingAs($owner);

    withVendorBillingTenant($tenant, function () use ($subscription, $invoice): void {
        expect(BillingStatusWidget::canView())->toBeFalse();

        $subscription->update(['status' => SubscriptionStatus::PastDue]);

        expect(BillingStatusWidget::canView())->toBeTrue();

        $subscription->update(['status' => SubscriptionStatus::Active]);
        $invoice->update(['status' => InvoiceStatus::Overdue]);

        $widget = new BillingStatusWidget;
        $data = $widget->billingStatusData();

        expect(BillingStatusWidget::canView())->toBeTrue()
            ->and($data['subscription']->id)->toBe($subscription->id)
            ->and($data['overdueInvoicesCount'])->toBe(1)
            ->and($data['overdueBalanceMinor'])->toBe(100000)
            ->and($data['severity'])->toBe('danger')
            ->and($data['overdueInvoices']->pluck('id')->all())->toBe([$invoice->id]);
    });
});

function withVendorBillingTenant(Tenant $tenant, Closure $callback): void
{
    $currentTenant = app(CurrentTenant::class);
    $currentTenant->set($tenant);

    try {
        $callback();
    } finally {
        $currentTenant->forget();
    }
}

function createVendorBillingFilamentPlan(): Plan
{
    $plan = Plan::query()->create([
        'name' => 'Vendor Billing Filament',
        'slug' => 'vendor-billing-filament-'.str()->random(8),
        'price_minor' => 100000,
        'currency' => 'DZD',
        'billing_interval' => 'monthly',
        'is_active' => true,
        'sort_order' => 10,
        'metadata' => [],
    ]);

    PlanFeature::query()->create([
        'plan_id' => $plan->id,
        'key' => PlanFeatureKey::MaxProducts->value,
        'value' => ['value' => 100],
    ]);

    return $plan;
}
