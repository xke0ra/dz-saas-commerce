<?php

use App\Actions\Billing\ConfirmSubscriptionPayment;
use App\Actions\Billing\RecordSubscriptionPayment;
use App\Actions\Billing\RejectSubscriptionPayment;
use App\Actions\Billing\StartTenantSubscription;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PlanFeatureKey;
use App\Enums\PlatformRole;
use App\Enums\SubscriptionPaymentMethod;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionPaymentRejectedNotification;
use App\Support\Billing\SubscriptionFeatureGate;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

it('starts a tenant subscription and issues an invoice', function (): void {
    $this->travelTo(now()->setDate(2026, 4, 25)->setTime(10, 0));

    $actor = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $plan = createBillingPlan('pro', 590000, [
        PlanFeatureKey::MaxProducts->value => 1000,
        PlanFeatureKey::CustomDomain->value => true,
    ]);

    $subscription = app(StartTenantSubscription::class)->handle($tenant, $plan, actor: $actor);
    $invoice = Invoice::query()->withoutGlobalScope('current_tenant')->where('subscription_id', $subscription->id)->firstOrFail();

    expect($subscription->tenant_id)->toBe($tenant->id)
        ->and($subscription->plan_id)->toBe($plan->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->is_current)->toBeTrue()
        ->and($subscription->current_period_ends_at->toDateString())->toBe('2026-05-25')
        ->and($invoice->tenant_id)->toBe($tenant->id)
        ->and($invoice->type)->toBe(InvoiceType::SubscriptionInitial)
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->total_minor)->toBe(590000)
        ->and($invoice->balance_minor)->toBe(590000)
        ->and($invoice->currency)->toBe('DZD')
        ->and($invoice->billing_period_starts_at->toDateString())->toBe('2026-04-25')
        ->and($invoice->billing_period_ends_at->toDateString())->toBe('2026-05-25');

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'actor_id' => $actor->id,
        'event' => 'subscription.started',
        'auditable_type' => $subscription->getMorphClass(),
        'auditable_id' => $subscription->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'actor_id' => $actor->id,
        'event' => 'invoice.issued',
        'auditable_type' => $invoice->getMorphClass(),
        'auditable_id' => $invoice->id,
    ]);
});

it('replaces the current subscription when changing plans', function (): void {
    $tenant = Tenant::factory()->create();
    $basic = createBillingPlan('basic', 250000, [PlanFeatureKey::MaxProducts->value => 100]);
    $business = createBillingPlan('business', 1290000, [PlanFeatureKey::MaxProducts->value => 10000]);

    $oldSubscription = app(StartTenantSubscription::class)->handle($tenant, $basic, createInvoice: false);
    $newSubscription = app(StartTenantSubscription::class)->handle($tenant, $business, createInvoice: false);

    expect($oldSubscription->refresh()->is_current)->toBeFalse()
        ->and($oldSubscription->refresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($newSubscription->is_current)->toBeTrue()
        ->and(Subscription::query()->withoutGlobalScope('current_tenant')->where('tenant_id', $tenant->id)->where('is_current', true)->count())->toBe(1);
});

it('resolves plan features and blocks limit overages', function (): void {
    $tenant = Tenant::factory()->create();
    $plan = createBillingPlan('starter', 100000, [
        PlanFeatureKey::MaxProducts->value => 1,
        PlanFeatureKey::CustomDomain->value => true,
    ]);
    app(StartTenantSubscription::class)->handle($tenant, $plan, createInvoice: false);

    Product::factory()->create(['tenant_id' => $tenant->id]);

    $gate = app(SubscriptionFeatureGate::class);

    expect($gate->limit($tenant, PlanFeatureKey::MaxProducts))->toBe(1)
        ->and($gate->enabled($tenant, PlanFeatureKey::CustomDomain))->toBeTrue()
        ->and($gate->usage($tenant, PlanFeatureKey::MaxProducts))->toBe(1);

    expect(fn (): mixed => $gate->ensureWithinLimit($tenant, PlanFeatureKey::MaxProducts))
        ->toThrow(ValidationException::class);
});

it('treats null feature limits as unlimited', function (): void {
    $tenant = Tenant::factory()->create();
    $plan = createBillingPlan('enterprise', 0, [
        PlanFeatureKey::MaxProducts->value => null,
    ]);
    app(StartTenantSubscription::class)->handle($tenant, $plan, createInvoice: false);

    $gate = app(SubscriptionFeatureGate::class);

    expect($gate->limit($tenant, PlanFeatureKey::MaxProducts))->toBeNull();

    $gate->ensureWithinLimit($tenant, PlanFeatureKey::MaxProducts, increment: 1000000);

    expect(true)->toBeTrue();
});

it('denies feature access when the subscription is expired', function (): void {
    $tenant = Tenant::factory()->create();
    $plan = createBillingPlan('expired-test', 0, [
        PlanFeatureKey::CustomDomain->value => true,
    ]);
    $subscription = app(StartTenantSubscription::class)->handle($tenant, $plan, createInvoice: false);

    $subscription->update(['status' => SubscriptionStatus::Expired]);

    expect(app(SubscriptionFeatureGate::class)->enabled($tenant, PlanFeatureKey::CustomDomain))->toBeFalse();
});

it('confirms manual subscription payments and marks invoices as paid', function (): void {
    $actor = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $plan = createBillingPlan('manual-pay', 250000, [
        PlanFeatureKey::MaxProducts->value => 100,
    ]);
    $subscription = app(StartTenantSubscription::class)->handle($tenant, $plan, actor: $actor);
    $invoice = Invoice::query()->withoutGlobalScope('current_tenant')->where('subscription_id', $subscription->id)->firstOrFail();

    $payment = app(RecordSubscriptionPayment::class)->handle(
        invoice: $invoice,
        amountMinor: 250000,
        method: SubscriptionPaymentMethod::ManualBankTransfer,
        reference: 'BANK-001',
        actor: $actor,
    );

    $confirmedPayment = app(ConfirmSubscriptionPayment::class)->handle($payment, $actor);
    $invoice->refresh();

    expect($payment->status)->toBe(SubscriptionPaymentStatus::Pending)
        ->and($confirmedPayment->status)->toBe(SubscriptionPaymentStatus::Confirmed)
        ->and($confirmedPayment->confirmed_by)->toBe($actor->id)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_amount_minor)->toBe(250000)
        ->and($invoice->balance_minor)->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'actor_id' => $actor->id,
        'event' => 'subscription_payment.confirmed',
        'auditable_type' => $payment->getMorphClass(),
        'auditable_id' => $payment->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'actor_id' => $actor->id,
        'event' => 'invoice.paid',
        'auditable_type' => $invoice->getMorphClass(),
        'auditable_id' => $invoice->id,
    ]);
});

it('rejects pending subscription payments with a reason and notifies the tenant owner', function (): void {
    Notification::fake();

    $actor = User::factory()->create();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);
    $plan = createBillingPlan('reject-pay', 250000, [
        PlanFeatureKey::MaxProducts->value => 100,
    ]);
    $subscription = app(StartTenantSubscription::class)->handle($tenant, $plan, actor: $actor);
    $invoice = Invoice::query()->withoutGlobalScope('current_tenant')->where('subscription_id', $subscription->id)->firstOrFail();

    $payment = app(RecordSubscriptionPayment::class)->handle(
        invoice: $invoice,
        amountMinor: 250000,
        method: SubscriptionPaymentMethod::ManualPaymentProof,
        reference: 'BAD-PROOF',
        proofPath: 'subscription-payment-proofs/bad-proof.jpg',
        actor: $actor,
    );

    $rejectedPayment = app(RejectSubscriptionPayment::class)->handle($payment, $actor, 'The uploaded proof is unreadable.');
    $invoice->refresh();

    expect($rejectedPayment->status)->toBe(SubscriptionPaymentStatus::Rejected)
        ->and($rejectedPayment->rejected_by)->toBe($actor->id)
        ->and($rejectedPayment->rejected_at)->not->toBeNull()
        ->and($rejectedPayment->rejection_reason)->toBe('The uploaded proof is unreadable.')
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->paid_amount_minor)->toBe(0)
        ->and($invoice->balance_minor)->toBe(250000);

    Notification::assertSentTo($owner, SubscriptionPaymentRejectedNotification::class);

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'actor_id' => $actor->id,
        'event' => 'subscription_payment.rejected',
        'auditable_type' => $payment->getMorphClass(),
        'auditable_id' => $payment->id,
    ]);
});

it('counts staff usage without counting the tenant owner', function (): void {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);
    $tenant->users()->attach($staff, ['role' => TenantRole::StoreStaff->value, 'permissions' => null]);
    $plan = createBillingPlan('staff-limit', 0, [
        PlanFeatureKey::MaxStaffUsers->value => 1,
    ]);
    app(StartTenantSubscription::class)->handle($tenant, $plan, createInvoice: false);

    expect(app(SubscriptionFeatureGate::class)->usage($tenant, PlanFeatureKey::MaxStaffUsers))->toBe(1);
});

it('protects tenant billing records with billing manage permission', function (): void {
    $billingUser = User::factory()->create();
    $plainStaff = User::factory()->create();
    $superAdmin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $plan = createBillingPlan('policy-plan', 100000, [PlanFeatureKey::MaxProducts->value => 100]);

    $tenant->users()->attach($billingUser, [
        'role' => TenantRole::StoreAdmin->value,
        'permissions' => json_encode([TenantPermission::BillingManage->value]),
    ]);
    $tenant->users()->attach($plainStaff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    app(CurrentTenant::class)->set($tenant);

    $subscription = app(StartTenantSubscription::class)->handle($tenant, $plan);
    $invoice = Invoice::query()->withoutGlobalScope('current_tenant')->where('subscription_id', $subscription->id)->firstOrFail();
    $payment = app(RecordSubscriptionPayment::class)->handle($invoice, 100000, SubscriptionPaymentMethod::ManualBankTransfer);
    $otherSubscription = app(StartTenantSubscription::class)->handle($otherTenant, $plan);
    $otherInvoice = Invoice::query()->withoutGlobalScope('current_tenant')->where('subscription_id', $otherSubscription->id)->firstOrFail();

    expect($billingUser->can('viewAny', Invoice::class))->toBeTrue()
        ->and($billingUser->can('view', $subscription))->toBeTrue()
        ->and($billingUser->can('view', $invoice))->toBeTrue()
        ->and($billingUser->can('view', $payment))->toBeTrue()
        ->and($billingUser->can('view', $otherInvoice))->toBeFalse()
        ->and($plainStaff->can('viewAny', Invoice::class))->toBeFalse()
        ->and($plainStaff->can('view', $invoice))->toBeFalse()
        ->and($superAdmin->can('confirm', $payment))->toBeTrue()
        ->and($superAdmin->can('reject', $payment))->toBeTrue();
});

/**
 * @param  array<string, mixed>  $features
 */
function createBillingPlan(string $slug, int $priceMinor, array $features): Plan
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

    foreach ($features as $key => $value) {
        PlanFeature::query()->create([
            'plan_id' => $plan->id,
            'key' => $key,
            'value' => ['value' => $value],
        ]);
    }

    return $plan;
}
