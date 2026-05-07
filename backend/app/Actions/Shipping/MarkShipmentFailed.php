<?php

namespace App\Actions\Shipping;

use App\Enums\ShipmentStatus;
use App\Models\FailedDeliveryReason;
use App\Models\Shipment;
use Illuminate\Validation\ValidationException;

class MarkShipmentFailed
{
    public function __construct(
        private readonly TransitionShipmentStatus $transitionShipmentStatus,
    ) {}

    public function handle(Shipment $shipment, FailedDeliveryReason $reason, ?string $failureNote = null): Shipment
    {
        if ($shipment->tenant_id !== $reason->tenant_id) {
            throw ValidationException::withMessages([
                'failed_delivery_reason_id' => 'The failed delivery reason does not belong to this shipment tenant.',
            ]);
        }

        return $this->transitionShipmentStatus->handle($shipment, ShipmentStatus::FailedDelivery, [
            'failed_delivery_reason_id' => $reason->id,
            'failure_note' => $failureNote,
        ]);
    }
}
