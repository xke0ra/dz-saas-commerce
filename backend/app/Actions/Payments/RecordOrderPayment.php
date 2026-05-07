<?php

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordOrderPayment
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Order $order,
        PaymentMethod $paymentMethod,
        int $amountMinor,
        ?string $reference = null,
        array $metadata = [],
    ): Payment {
        return DB::transaction(function () use ($order, $paymentMethod, $amountMinor, $reference, $metadata): Payment {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureOrderCanReceivePayment($lockedOrder);
            $this->ensurePaymentMethodCanBeUsed($lockedOrder, $paymentMethod);

            $outstandingAmount = $this->outstandingAmount($lockedOrder);

            if ($outstandingAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount_minor' => 'This order is already fully paid.',
                ]);
            }

            if ($amountMinor !== $outstandingAmount) {
                throw ValidationException::withMessages([
                    'amount_minor' => 'Payment amount must match the outstanding order total.',
                ]);
            }

            $payment = Payment::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $lockedOrder->tenant_id)
                ->where('order_id', $lockedOrder->id)
                ->where('status', PaymentStatus::Pending->value)
                ->lockForUpdate()
                ->oldest()
                ->first();

            if ($payment === null) {
                $payment = Payment::query()
                    ->withoutGlobalScope('current_tenant')
                    ->create([
                        'tenant_id' => $lockedOrder->tenant_id,
                        'order_id' => $lockedOrder->id,
                        'payment_method_id' => $paymentMethod->id,
                        'status' => PaymentStatus::Pending,
                        'amount_minor' => $amountMinor,
                        'currency' => $lockedOrder->currency,
                        'reference' => $reference,
                        'metadata' => $metadata,
                    ]);
            }

            $payment->update([
                'payment_method_id' => $paymentMethod->id,
                'status' => PaymentStatus::Paid,
                'amount_minor' => $amountMinor,
                'currency' => $lockedOrder->currency,
                'reference' => $reference,
                'metadata' => array_merge($payment->metadata ?? [], $metadata),
                'paid_at' => now(),
            ]);

            $lockedOrder->update([
                'payment_status' => PaymentStatus::Paid,
            ]);

            return $payment->refresh()->load(['order', 'paymentMethod']);
        });
    }

    public function outstandingAmount(Order $order): int
    {
        $paidAmount = Payment::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount_minor');

        return max(0, $order->total_minor - $paidAmount);
    }

    private function ensureOrderCanReceivePayment(Order $order): void
    {
        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            throw ValidationException::withMessages([
                'order_id' => 'Cancelled or refunded orders cannot receive payments.',
            ]);
        }

        if (in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)) {
            throw ValidationException::withMessages([
                'payment_status' => 'This order payment status does not allow recording a new payment.',
            ]);
        }
    }

    private function ensurePaymentMethodCanBeUsed(Order $order, PaymentMethod $paymentMethod): void
    {
        if ($paymentMethod->tenant_id !== $order->tenant_id) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'The payment method does not belong to this order tenant.',
            ]);
        }

        if (! $paymentMethod->is_active) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'The selected payment method is not active.',
            ]);
        }
    }
}
