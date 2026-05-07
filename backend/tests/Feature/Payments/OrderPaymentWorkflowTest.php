<?php

use App\Actions\Payments\MarkOrderPaymentFailed;
use App\Actions\Payments\RecordOrderPayment;
use App\Actions\Payments\RefundOrderPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Commune;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wilaya;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->wilaya = Wilaya::query()->findOrFail(16);
    $this->commune = Commune::query()
        ->where('wilaya_id', $this->wilaya->id)
        ->firstOrFail();
});

it('records a full manual order payment and marks the order as paid', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = createPaymentWorkflowOrder($tenant, $this->wilaya->id, $this->commune->id);
    $paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => PaymentMethodType::ManualBankTransfer,
        'name' => 'Manual bank transfer',
    ]);
    $payment = createPendingPayment($order, $paymentMethod);

    attachPaymentWorkflowUser($user, $tenant);

    $this->actingAs($user);

    app(RecordOrderPayment::class)->handle(
        order: $order,
        paymentMethod: $paymentMethod,
        amountMinor: $order->total_minor,
        reference: 'BANK-REF-100',
        metadata: ['source' => 'bank_statement'],
    );

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->reference)->toBe('BANK-REF-100')
        ->and($payment->metadata['source'])->toBe('bank_statement')
        ->and($order->payment_status)->toBe(PaymentStatus::Paid);

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'event' => 'payment.confirmed',
        'auditable_type' => $payment->getMorphClass(),
        'auditable_id' => $payment->id,
    ]);
});

it('rejects recording a payment with a mismatched amount', function (): void {
    $tenant = Tenant::factory()->create();
    $order = createPaymentWorkflowOrder($tenant, $this->wilaya->id, $this->commune->id);
    $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    createPendingPayment($order, $paymentMethod);

    app(RecordOrderPayment::class)->handle(
        order: $order,
        paymentMethod: $paymentMethod,
        amountMinor: $order->total_minor - 100,
    );
})->throws(
    ValidationException::class,
    'Payment amount must match the outstanding order total.',
);

it('marks a pending order payment as failed', function (): void {
    $tenant = Tenant::factory()->create();
    $order = createPaymentWorkflowOrder($tenant, $this->wilaya->id, $this->commune->id);
    $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);
    $payment = createPendingPayment($order, $paymentMethod);

    app(MarkOrderPaymentFailed::class)->handle($order, 'Customer refused COD payment.');

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->metadata['failure_reason'])->toBe('Customer refused COD payment.')
        ->and($order->payment_status)->toBe(PaymentStatus::Failed);
});

it('refunds paid order payments and marks the order as refunded', function (): void {
    $tenant = Tenant::factory()->create();
    $order = createPaymentWorkflowOrder(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        paymentStatus: PaymentStatus::Paid,
    );
    $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);
    $payment = Payment::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => PaymentStatus::Paid,
        'amount_minor' => $order->total_minor,
        'currency' => $order->currency,
        'reference' => 'COD-PAID-1',
        'metadata' => [],
        'paid_at' => now(),
    ]);

    app(RefundOrderPayment::class)->handle($order, 'Returned by customer.');

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->metadata['refund_reason'])->toBe('Returned by customer.')
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded);
});

it('protects order payment actions with the payments manage permission', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = createPaymentWorkflowOrder($tenant, $this->wilaya->id, $this->commune->id);
    $otherTenant = Tenant::factory()->create();
    $otherOrder = createPaymentWorkflowOrder($otherTenant, $this->wilaya->id, $this->commune->id);

    attachPaymentWorkflowUser($user, $tenant, permissions: [
        TenantPermission::PaymentsManage->value => false,
    ]);

    expect($user->can('collectPayment', $order))->toBeFalse()
        ->and($user->can('failPayment', $order))->toBeFalse()
        ->and($user->can('refundPayment', $order))->toBeFalse()
        ->and($user->can('collectPayment', $otherOrder))->toBeFalse();
});

function createPaymentWorkflowOrder(
    Tenant $tenant,
    int $wilayaId,
    int $communeId,
    PaymentStatus $paymentStatus = PaymentStatus::Unpaid,
): Order {
    $store = Store::factory()->for($tenant)->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
    ]);

    return Order::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Delivered,
        'payment_status' => $paymentStatus,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
    ]);
}

function createPendingPayment(Order $order, PaymentMethod $paymentMethod): Payment
{
    return Payment::query()->create([
        'tenant_id' => $order->tenant_id,
        'order_id' => $order->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => PaymentStatus::Pending,
        'amount_minor' => $order->total_minor,
        'currency' => $order->currency,
        'reference' => null,
        'metadata' => [],
    ]);
}

/**
 * @param  array<string, bool>|null  $permissions
 */
function attachPaymentWorkflowUser(
    User $user,
    Tenant $tenant,
    TenantRole $role = TenantRole::StoreStaff,
    ?array $permissions = null,
): void {
    $tenant->users()->attach($user, [
        'role' => $role->value,
        'permissions' => $permissions === null ? null : json_encode($permissions),
    ]);
}
