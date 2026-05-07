<?php

namespace App\Actions\Billing;

use App\Enums\SubscriptionPaymentStatus;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SubscriptionPaymentRejectedNotification;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectSubscriptionPayment
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SubscriptionPayment $payment, User $rejectedBy, string $reason): SubscriptionPayment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('A rejection reason is required.'),
            ]);
        }

        return DB::transaction(function () use ($payment, $rejectedBy, $reason): SubscriptionPayment {
            $payment = SubscriptionPayment::query()
                ->withoutGlobalScope('current_tenant')
                ->with(['tenant.owner', 'invoice'])
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== SubscriptionPaymentStatus::Pending) {
                throw ValidationException::withMessages([
                    'payment' => __('Only pending subscription payments can be rejected.'),
                ]);
            }

            $oldStatus = $payment->status;

            $payment->update([
                'status' => SubscriptionPaymentStatus::Rejected,
                'rejected_by' => $rejectedBy->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $payment->refresh()->load(['tenant.owner', 'invoice']);

            $this->auditLogger->record(
                event: 'subscription_payment.rejected',
                auditable: $payment,
                actor: $rejectedBy,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => $payment->status,
                    'rejected_by' => $rejectedBy->id,
                    'rejected_at' => $payment->rejected_at,
                    'rejection_reason' => $reason,
                ],
            );

            $payment->tenant->owner?->notify(new SubscriptionPaymentRejectedNotification($payment));

            return $payment;
        });
    }
}
