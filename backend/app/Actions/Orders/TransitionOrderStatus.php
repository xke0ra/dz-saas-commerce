<?php

namespace App\Actions\Orders;

use App\Actions\Inventory\ReleaseOrderInventoryReservations;
use App\Actions\Inventory\SettleOrderInventory;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionOrderStatus
{
    /**
     * @var array<string, array<int, OrderStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        OrderStatus::Draft->value => [
            OrderStatus::Pending,
            OrderStatus::Cancelled,
        ],
        OrderStatus::Pending->value => [
            OrderStatus::Confirmed,
            OrderStatus::Cancelled,
        ],
        OrderStatus::Confirmed->value => [
            OrderStatus::Processing,
            OrderStatus::Packed,
            OrderStatus::Shipped,
            OrderStatus::Cancelled,
        ],
        OrderStatus::Processing->value => [
            OrderStatus::Packed,
            OrderStatus::Shipped,
            OrderStatus::Cancelled,
        ],
        OrderStatus::Packed->value => [
            OrderStatus::Shipped,
            OrderStatus::Cancelled,
        ],
        OrderStatus::Shipped->value => [
            OrderStatus::OutForDelivery,
            OrderStatus::Delivered,
            OrderStatus::FailedDelivery,
            OrderStatus::Returned,
        ],
        OrderStatus::OutForDelivery->value => [
            OrderStatus::Delivered,
            OrderStatus::FailedDelivery,
            OrderStatus::Returned,
        ],
        OrderStatus::FailedDelivery->value => [
            OrderStatus::OutForDelivery,
            OrderStatus::Returned,
            OrderStatus::Cancelled,
        ],
        OrderStatus::Delivered->value => [
            OrderStatus::Returned,
            OrderStatus::Refunded,
        ],
        OrderStatus::Returned->value => [
            OrderStatus::Refunded,
        ],
    ];

    public function handle(Order $order, OrderStatus $targetStatus): Order
    {
        return DB::transaction(function () use ($order, $targetStatus): Order {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $lockedOrder->status;

            if ($currentStatus === $targetStatus) {
                return $lockedOrder;
            }

            if (! $this->canTransition($currentStatus, $targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Cannot transition order status from %s to %s.',
                        $currentStatus->getLabel(),
                        $targetStatus->getLabel(),
                    ),
                ]);
            }

            $attributes = [
                'status' => $targetStatus,
            ];

            if ($targetStatus === OrderStatus::Confirmed && $lockedOrder->confirmed_at === null) {
                $attributes['confirmed_at'] = now();
            }

            $lockedOrder->update($attributes);
            $this->syncInventory($lockedOrder, $targetStatus);

            return $lockedOrder->refresh();
        });
    }

    public function canTransition(OrderStatus $currentStatus, OrderStatus $targetStatus): bool
    {
        if ($currentStatus === $targetStatus) {
            return true;
        }

        return in_array($targetStatus, $this->allowedTargets($currentStatus), true);
    }

    /**
     * @return array<int, OrderStatus>
     */
    public function allowedTargets(OrderStatus $currentStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStatus->value] ?? [];
    }

    private function syncInventory(Order $order, OrderStatus $targetStatus): void
    {
        match ($targetStatus) {
            OrderStatus::Delivered => app(SettleOrderInventory::class)->handle($order),
            OrderStatus::Cancelled, OrderStatus::Returned => app(ReleaseOrderInventoryReservations::class)->handle($order),
            default => null,
        };
    }
}
