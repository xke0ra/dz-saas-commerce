<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkOrderPaymentFailed
{
    public function handle(Order $order, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($order, $reason): Payment {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedOrder->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Paid or refunded orders cannot be marked as payment failed.',
                ]);
            }

            $payment = Payment::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $lockedOrder->tenant_id)
                ->where('order_id', $lockedOrder->id)
                ->where('status', PaymentStatus::Pending->value)
                ->lockForUpdate()
                ->latest()
                ->first();

            if ($payment === null) {
                throw ValidationException::withMessages([
                    'payment' => 'This order does not have a pending payment to fail.',
                ]);
            }

            $payment->update([
                'status' => PaymentStatus::Failed,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'failure_reason' => $reason,
                ]),
            ]);

            $lockedOrder->update([
                'payment_status' => PaymentStatus::Failed,
            ]);

            return $payment->refresh()->load(['order', 'paymentMethod']);
        });
    }
}
