<?php

namespace App\Actions\Shipping;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;

class MarkShipmentOutForDelivery
{
    public function __construct(
        private readonly TransitionShipmentStatus $transitionShipmentStatus,
    ) {}

    public function handle(Shipment $shipment): Shipment
    {
        return $this->transitionShipmentStatus->handle($shipment, ShipmentStatus::OutForDelivery);
    }
}
