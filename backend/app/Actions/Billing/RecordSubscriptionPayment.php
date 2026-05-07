<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionPaymentMethod;
use App\Enums\SubscriptionPaymentStatus;
use App\Models\Invoice;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class RecordSubscriptionPayment
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        Invoice $invoice,
        int $amountMinor,
        SubscriptionPaymentMethod|string $method,
        ?string $reference = null,
        ?string $proofPath = null,
        ?array $metadata = null,
        ?User $actor = null,
    ): SubscriptionPayment {
        $invoice->refresh();
        $method = $method instanceof SubscriptionPaymentMethod ? $method : SubscriptionPaymentMethod::from($method);

        if ($invoice->status === InvoiceStatus::Void || $invoice->status === InvoiceStatus::Paid) {
            throw ValidationException::withMessages([
                'invoice' => __('Payments can only be recorded against open invoices.'),
            ]);
        }

        if ($amountMinor <= 0 || $amountMinor > $invoice->balance_minor) {
            throw ValidationException::withMessages([
                'amount_minor' => __('The payment amount must be positive and not exceed the invoice balance.'),
            ]);
        }

        $payment = SubscriptionPayment::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'subscription_id' => $invoice->subscription_id,
            'invoice_id' => $invoice->id,
            'status' => SubscriptionPaymentStatus::Pending,
            'method' => $method,
            'amount_minor' => $amountMinor,
            'currency' => $invoice->currency,
            'reference' => $reference,
            'proof_path' => $proofPath,
            'metadata' => $metadata ?? [],
        ]);

        $this->auditLogger->record(
            event: 'subscription_payment.recorded',
            auditable: $payment,
            actor: $actor,
            newValues: [
                'amount_minor' => $payment->amount_minor,
                'method' => $payment->method,
                'status' => $payment->status,
            ],
        );

        return $payment;
    }
}
