<?php

use App\Actions\Billing\ConfirmSubscriptionPayment;
use App\Actions\Billing\ProcessBillingLifecycle;
use App\Actions\Billing\RecordSubscriptionPayment;
use App\Actions\Billing\StartTenantSubscription;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PlanFeatureKey;
use App\Enums\StoreStatus;
use App\Enums\SubscriptionPaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Jobs\Billing\ProcessBillingLifecycleJob;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionGracePeriodStartedNotification;
use App\Notifications\SubscriptionRenewalReminderNotification;
use App\Notifications\SubscriptionSuspendedNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

it('sends renewal reminders once per configured reminder day', function (): void {
    Notification::fake();
    $this->travelTo(now()->setDate(2026, 4, 25)->setTime(9, 0));

    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);
    $plan = makeLifecyclePlan('reminder-plan');
    $subscription = app(StartTenantSubscription::class)->handle(
        tenant: $tenant,
        plan: $plan,
        startsAt: now()->subDays(23),
        createInvoice: false,
    );

    expect($subscription->current_period_ends_at->toDateString())->toBe('2026-05-02');

    $counts = app(ProcessBillingLifecycle::class)->handle(now());
    $again = app(ProcessBillingLifecycle::class)->handle(now());

    expect($counts['renewal_reminders_sent'])->toBe(1)
        ->and($again['renewal_reminders_sent'])->toBe(0)
        ->and($subscription->refresh()->metadata['billing.reminders_sent.2026-05-02.7'])->toBeTrue();

    Notification::assertSentToTimes($owner, SubscriptionRenewalReminderNotification::class, 1);
});

it('issues one renewal invoice before the subscription period ends', function (): void {
    $this->travelTo(now()->setDate(2026, 4, 25)->setTime(9, 0));

    $tenant = Tenant::factory()->create();
    $plan = makeLifecyclePlan('renewal-invoice-plan', 250000);
    $subscription = app(StartTenantSubscription::class)->handle(
        tenant: $tenant,
        plan: $plan,
        startsAt: now()->subDays(23),
        createInvoice: false,
    );

    $counts = app(ProcessBillingLifecycle::class)->handle(now());
    $again = app(ProcessBillingLifecycle::class)->handle(now());

    $invoice = Invoice::query()
        ->withoutGlobalScope('current_tenant')
        ->where('subscription_id', $subscription->id)
        ->where('type', InvoiceType::SubscriptionRenewal)
        ->firstOrFail();

    expect($counts['renewal_invoices_issued'])->toBe(1)
        ->and($again['renewal_invoices_issued'])->toBe(0)
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->total_minor)->toBe(250000)
        ->and($invoice->due_at->toDateString())->toBe('2026-05-02')
        ->and($invoice->billing_period_starts_at->toDateString())->toBe('2026-05-02')
        ->and($invoice->billing_period_ends_at->toDateString())->toBe('2026-06-02')
        ->and(
            Invoice::query()
                ->withoutGlobalScope('current_tenant')
                ->where('subscription_id', $subscription->id)
                ->where('type', InvoiceType::SubscriptionRenewal)
                ->count()
        )->toBe(1);
});

it('extends the subscription period when a renewal invoice is paid', function (): void {
    $this->travelTo(now()->setDate(2026, 4, 25)->setTime(9, 0));

    $actor = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $plan = makeLifecyclePlan('paid-renewal-plan', 250000);
    $subscription = app(StartTenantSubscription::class)->handle(
        tenant: $tenant,
        plan: $plan,
        startsAt: now()->subDays(23),
        createInvoice: false,
    );

    app(ProcessBillingLifecycle::class)->handle(now());

    $invoice = Invoice::query()
        ->withoutGlobalScope('current_tenant')
        ->where('subscription_id', $subscription->id)
        ->where('type', InvoiceType::SubscriptionRenewal)
        ->firstOrFail();

    $payment = app(RecordSubscriptionPayment::class)->handle(
        invoice: $invoice,
        amountMinor: 250000,
        method: SubscriptionPaymentMethod::ManualBankTransfer,
        reference: 'RENEW-001',
        actor: $actor,
    );

    app(ConfirmSubscriptionPayment::class)->handle($payment, $actor);

    $subscription->refresh();

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_starts_at->toDateString())->toBe('2026-05-02')
        ->and($subscription->current_period_ends_at->toDateString())->toBe('2026-06-02')
        ->and($subscription->grace_ends_at)->toBeNull()
        ->and($subscription->ends_at)->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'event' => 'subscription.renewed',
        'auditable_type' => $subscription->getMorphClass(),
        'auditable_id' => $subscription->id,
    ]);
});

it('marks overdue invoices and current subscriptions as past due', function (): void {
    $this->travelTo(now()->setDate(2026, 4, 25)->setTime(9, 0));

    $tenant = Tenant::factory()->create();
    $plan = makeLifecyclePlan('overdue-plan', 250000);
    $subscription = app(StartTenantSubscription::class)->handle($tenant, $plan);
    $invoice = Invoice::query()
        ->withoutGlobalScope('current_tenant')
        ->where('subscription_id', $subscription->id)
        ->firstOrFail();

    $invoice->update(['due_at' => now()->subDay()]);

    $counts = app(ProcessBillingLifecycle::class)->handle(now());

    expect($counts['invoices_marked_overdue'])->toBe(1)
        ->and($counts['subscriptions_marked_past_due'])->toBe(1)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::PastDue);

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'event' => 'invoice.overdue',
        'auditable_type' => $invoice->getMorphClass(),
        'auditable_id' => $invoice->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'event' => 'subscription.past_due',
        'auditable_type' => $subscription->getMorphClass(),
        'auditable_id' => $subscription->id,
    ]);
});

it('starts grace period after period end and suspends tenant stores after grace ends', function (): void {
    Notification::fake();
    config()->set('billing.grace_period_days', 7);
    $this->travelTo(now()->setDate(2026, 4, 25)->setTime(9, 0));

    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'owner_id' => $owner->id,
        'status' => TenantStatus::Active,
    ]);
    $store = Store::factory()->for($tenant)->create(['status' => StoreStatus::Active]);
    $plan = makeLifecyclePlan('grace-plan');
    $subscription = app(StartTenantSubscription::class)->handle(
        tenant: $tenant,
        plan: $plan,
        startsAt: now()->subMonth()->subDay(),
        createInvoice: false,
    );

    $graceCounts = app(ProcessBillingLifecycle::class)->handle(now());
    $subscription->refresh();

    expect($graceCounts['grace_periods_started'])->toBe(1)
        ->and($subscription->status)->toBe(SubscriptionStatus::GracePeriod)
        ->and($subscription->grace_ends_at->toDateString())->toBe('2026-05-02')
        ->and($tenant->refresh()->status)->toBe(TenantStatus::Active)
        ->and($store->refresh()->status)->toBe(StoreStatus::Active);

    Notification::assertSentTo($owner, SubscriptionGracePeriodStartedNotification::class);

    $this->travelTo(now()->setDate(2026, 5, 3)->setTime(9, 0));

    $suspensionCounts = app(ProcessBillingLifecycle::class)->handle(now());

    expect($suspensionCounts['subscriptions_suspended'])->toBe(1)
        ->and($suspensionCounts['tenants_suspended'])->toBe(1)
        ->and($suspensionCounts['stores_suspended'])->toBe(1)
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Suspended)
        ->and($tenant->refresh()->status)->toBe(TenantStatus::Suspended)
        ->and($store->refresh()->status)->toBe(StoreStatus::Suspended);

    Notification::assertSentTo($owner, SubscriptionSuspendedNotification::class);
});

it('reactivates billing suspended tenants and stores after a renewal payment is confirmed', function (): void {
    Notification::fake();
    config()->set('billing.grace_period_days', 7);
    $this->travelTo(now()->setDate(2026, 4, 25)->setTime(9, 0));

    $actor = User::factory()->create();
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    $store = Store::factory()->for($tenant)->create(['status' => StoreStatus::Active]);
    $plan = makeLifecyclePlan('reactivation-plan', 250000);
    $subscription = app(StartTenantSubscription::class)->handle(
        tenant: $tenant,
        plan: $plan,
        startsAt: now()->subMonth()->subDay(),
        createInvoice: false,
    );

    app(ProcessBillingLifecycle::class)->handle(now());

    $invoice = Invoice::query()
        ->withoutGlobalScope('current_tenant')
        ->where('subscription_id', $subscription->id)
        ->where('type', InvoiceType::SubscriptionRenewal)
        ->firstOrFail();

    $this->travelTo(now()->setDate(2026, 5, 3)->setTime(9, 0));

    app(ProcessBillingLifecycle::class)->handle(now());

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Suspended)
        ->and($tenant->refresh()->status)->toBe(TenantStatus::Suspended)
        ->and($store->refresh()->status)->toBe(StoreStatus::Suspended)
        ->and($tenant->settings['billing_suspension']['subscription_id'])->toBe($subscription->id)
        ->and($store->settings['billing_suspension']['subscription_id'])->toBe($subscription->id)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Overdue);

    $payment = app(RecordSubscriptionPayment::class)->handle(
        invoice: $invoice,
        amountMinor: 250000,
        method: SubscriptionPaymentMethod::ManualBankTransfer,
        reference: 'LATE-RENEW-001',
        actor: $actor,
    );

    app(ConfirmSubscriptionPayment::class)->handle($payment, $actor);

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($tenant->refresh()->status)->toBe(TenantStatus::Active)
        ->and($store->refresh()->status)->toBe(StoreStatus::Active)
        ->and($tenant->settings)->not->toHaveKey('billing_suspension')
        ->and($store->settings)->not->toHaveKey('billing_suspension');
});

it('runs billing lifecycle through artisan sync mode and queues the default mode', function (): void {
    Bus::fake();

    expect(Artisan::call('billing:process --sync'))->toBe(0);

    expect(Artisan::call('billing:process'))->toBe(0);

    Bus::assertDispatched(ProcessBillingLifecycleJob::class);
});

function makeLifecyclePlan(string $slug, int $priceMinor = 0): Plan
{
    $plan = Plan::query()->create([
        'name' => str($slug)->replace('-', ' ')->headline()->toString(),
        'slug' => $slug,
        'price_minor' => $priceMinor,
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
