<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundOrderPayment
{
    /**
     * @return Collection<int, Payment>
     */
    public function handle(Order $order, ?string $reason = null): Collection
    {
        return DB::transaction(function () use ($order, $reason): Collection {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->payment_status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Only paid orders can be refunded.',
                ]);
            }

            /** @var Collection<int, Payment> $payments */
            $payments = Payment::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $lockedOrder->tenant_id)
                ->where('order_id', $lockedOrder->id)
                ->where('status', PaymentStatus::Paid->value)
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                throw ValidationException::withMessages([
                    'payment' => 'This order does not have a paid payment to refund.',
                ]);
            }

            foreach ($payments as $payment) {
                $payment->update([
                    'status' => PaymentStatus::Refunded,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'refund_reason' => $reason,
                        'refunded_at' => now()->toISOString(),
                    ]),
                ]);
            }

            $lockedOrder->update([
                'payment_status' => PaymentStatus::Refunded,
            ]);

            return $payments->map->refresh();
        });
    }
}
