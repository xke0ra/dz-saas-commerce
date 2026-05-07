<?php

namespace App\Actions\Shipping;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateShipmentForOrder
{
    /**
     * @var array<int, OrderStatus>
     */
    private const SHIPPABLE_ORDER_STATUSES = [
        OrderStatus::Confirmed,
        OrderStatus::Processing,
        OrderStatus::Packed,
    ];

    /**
     * @var array<int, ShipmentStatus>
     */
    private const CLOSED_SHIPMENT_STATUSES = [
        ShipmentStatus::Cancelled,
        ShipmentStatus::Returned,
    ];

    public function handle(Order $order, ?ShippingCompany $shippingCompany = null, ?string $trackingNumber = null): Shipment
    {
        return DB::transaction(function () use ($order, $shippingCompany, $trackingNumber): Shipment {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureOrderCanBeShipped($lockedOrder);

            if ($shippingCompany !== null) {
                $this->ensureShippingCompanyCanHandleOrder($lockedOrder, $shippingCompany);
            }

            $shipment = Shipment::query()->create([
                'tenant_id' => $lockedOrder->tenant_id,
                'order_id' => $lockedOrder->id,
                'shipping_company_id' => $shippingCompany?->id,
                'tracking_number' => $trackingNumber ?: $this->generateTrackingNumber($lockedOrder->tenant_id),
                'status' => ShipmentStatus::ReadyToShip,
                'delivery_type' => $lockedOrder->delivery_type,
                'wilaya_id' => $lockedOrder->wilaya_id,
                'commune_id' => $lockedOrder->commune_id,
                'destination_address' => $lockedOrder->shipping_address,
                'shipping_fee_minor' => $lockedOrder->shipping_fee_minor,
                'currency' => $lockedOrder->currency,
                'metadata' => [],
            ]);

            if (in_array($lockedOrder->status, [OrderStatus::Confirmed, OrderStatus::Processing], true)) {
                $lockedOrder->update([
                    'status' => OrderStatus::Packed,
                ]);
            }

            return $shipment->refresh()->load(['order', 'shippingCompany']);
        });
    }

    private function ensureOrderCanBeShipped(Order $order): void
    {
        if (! in_array($order->status, self::SHIPPABLE_ORDER_STATUSES, true)) {
            throw ValidationException::withMessages([
                'order_id' => 'Only confirmed, processing, or packed orders can be prepared for shipment.',
            ]);
        }

        $hasOpenShipment = $order->shipments()
            ->whereNotIn('status', array_map(
                fn (ShipmentStatus $status): string => $status->value,
                self::CLOSED_SHIPMENT_STATUSES,
            ))
            ->exists();

        if ($hasOpenShipment) {
            throw ValidationException::withMessages([
                'order_id' => 'This order already has an open shipment.',
            ]);
        }
    }

    private function ensureShippingCompanyCanHandleOrder(Order $order, ShippingCompany $shippingCompany): void
    {
        if ($shippingCompany->tenant_id !== $order->tenant_id) {
            throw ValidationException::withMessages([
                'shipping_company_id' => 'The shipping company does not belong to this order tenant.',
            ]);
        }

        if (! $shippingCompany->is_active) {
            throw ValidationException::withMessages([
                'shipping_company_id' => 'The selected shipping company is not active.',
            ]);
        }

        $supportsDeliveryType = match ($order->delivery_type) {
            DeliveryType::Home => $shippingCompany->supports_home_delivery,
            DeliveryType::Desk => $shippingCompany->supports_desk_delivery,
        };

        if (! $supportsDeliveryType) {
            throw ValidationException::withMessages([
                'shipping_company_id' => 'The selected shipping company does not support this delivery type.',
            ]);
        }
    }

    private function generateTrackingNumber(string $tenantId): string
    {
        do {
            $trackingNumber = 'SHP-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (
            Shipment::query()
                ->where('tenant_id', $tenantId)
                ->where('tracking_number', $trackingNumber)
                ->exists()
        );

        return $trackingNumber;
    }
}
