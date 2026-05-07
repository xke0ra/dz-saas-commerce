<?php

namespace App\Actions\Shipping;

use App\Actions\Inventory\ReleaseOrderInventoryReservations;
use App\Actions\Inventory\SettleOrderInventory;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionShipmentStatus
{
    /**
     * @var array<string, array<int, ShipmentStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        ShipmentStatus::Pending->value => [
            ShipmentStatus::ReadyToShip,
            ShipmentStatus::Cancelled,
        ],
        ShipmentStatus::ReadyToShip->value => [
            ShipmentStatus::Shipped,
            ShipmentStatus::Cancelled,
        ],
        ShipmentStatus::Shipped->value => [
            ShipmentStatus::InTransit,
            ShipmentStatus::OutForDelivery,
            ShipmentStatus::Delivered,
            ShipmentStatus::FailedDelivery,
            ShipmentStatus::Returned,
        ],
        ShipmentStatus::InTransit->value => [
            ShipmentStatus::OutForDelivery,
            ShipmentStatus::Delivered,
            ShipmentStatus::FailedDelivery,
            ShipmentStatus::Returned,
        ],
        ShipmentStatus::OutForDelivery->value => [
            ShipmentStatus::Delivered,
            ShipmentStatus::FailedDelivery,
            ShipmentStatus::Returned,
        ],
        ShipmentStatus::FailedDelivery->value => [
            ShipmentStatus::OutForDelivery,
            ShipmentStatus::Returned,
            ShipmentStatus::Cancelled,
        ],
        ShipmentStatus::Delivered->value => [
            ShipmentStatus::Returned,
        ],
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Shipment $shipment, ShipmentStatus $targetStatus, array $attributes = []): Shipment
    {
        return DB::transaction(function () use ($shipment, $targetStatus, $attributes): Shipment {
            $lockedShipment = Shipment::query()
                ->whereKey($shipment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $lockedShipment->status;

            if ($currentStatus !== $targetStatus && ! $this->canTransition($currentStatus, $targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Cannot transition shipment status from %s to %s.',
                        $currentStatus->getLabel(),
                        $targetStatus->getLabel(),
                    ),
                ]);
            }

            $attributes = array_merge($attributes, [
                'status' => $targetStatus,
            ]);

            if ($targetStatus === ShipmentStatus::Shipped && $lockedShipment->shipped_at === null) {
                $attributes['shipped_at'] = now();
            }

            if ($targetStatus === ShipmentStatus::Delivered && $lockedShipment->delivered_at === null) {
                $attributes['delivered_at'] = now();
            }

            if (
                $currentStatus === ShipmentStatus::FailedDelivery
                && in_array($targetStatus, [ShipmentStatus::OutForDelivery, ShipmentStatus::InTransit, ShipmentStatus::Shipped], true)
            ) {
                $attributes['failed_delivery_reason_id'] = null;
                $attributes['failure_note'] = null;
            }

            $lockedShipment->update($attributes);
            $this->syncOrderStatus($lockedShipment, $targetStatus);

            return $lockedShipment->refresh()->load(['order', 'failedDeliveryReason', 'shippingCompany']);
        });
    }

    public function canTransition(ShipmentStatus $currentStatus, ShipmentStatus $targetStatus): bool
    {
        if ($currentStatus === $targetStatus) {
            return true;
        }

        return in_array($targetStatus, $this->allowedTargets($currentStatus), true);
    }

    /**
     * @return array<int, ShipmentStatus>
     */
    public function allowedTargets(ShipmentStatus $currentStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStatus->value] ?? [];
    }

    private function syncOrderStatus(Shipment $shipment, ShipmentStatus $shipmentStatus): void
    {
        $orderStatus = match ($shipmentStatus) {
            ShipmentStatus::ReadyToShip => OrderStatus::Packed,
            ShipmentStatus::Shipped, ShipmentStatus::InTransit => OrderStatus::Shipped,
            ShipmentStatus::OutForDelivery => OrderStatus::OutForDelivery,
            ShipmentStatus::Delivered => OrderStatus::Delivered,
            ShipmentStatus::FailedDelivery => OrderStatus::FailedDelivery,
            ShipmentStatus::Returned => OrderStatus::Returned,
            default => null,
        };

        if ($orderStatus === null) {
            return;
        }

        $order = $shipment->order()->firstOrFail();

        $order->update([
            'status' => $orderStatus,
        ]);

        $this->syncOrderInventory($order, $orderStatus);
    }

    private function syncOrderInventory(Order $order, OrderStatus $orderStatus): void
    {
        match ($orderStatus) {
            OrderStatus::Delivered => app(SettleOrderInventory::class)->handle($order),
            OrderStatus::Cancelled, OrderStatus::Returned => app(ReleaseOrderInventoryReservations::class)->handle($order),
            default => null,
        };
    }
}
