<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Audit\AuditLogger;

class PaymentObserver
{
    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged('status')) {
            return;
        }

        $fromStatus = PaymentStatus::tryFrom((string) $payment->getRawOriginal('status'));

        app(AuditLogger::class)->record(
            event: $payment->status === PaymentStatus::Paid ? 'payment.confirmed' : 'payment.status_changed',
            auditable: $payment,
            oldValues: [
                'status' => $fromStatus,
            ],
            newValues: [
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toISOString(),
            ],
        );
    }
}
