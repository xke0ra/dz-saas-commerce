<?php

namespace App\Observers;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;

class ShipmentObserver
{
    public function created(Shipment $shipment): void
    {
        $shipment->statusHistories()->create([
            'tenant_id' => $shipment->tenant_id,
            'from_status' => null,
            'to_status' => $shipment->status,
            'comment' => 'Shipment created.',
            'changed_by_id' => auth()->id(),
        ]);
    }

    public function updated(Shipment $shipment): void
    {
        if (! $shipment->wasChanged('status')) {
            return;
        }

        $fromStatus = ShipmentStatus::tryFrom((string) $shipment->getRawOriginal('status'));

        $shipment->statusHistories()->create([
            'tenant_id' => $shipment->tenant_id,
            'from_status' => $fromStatus,
            'to_status' => $shipment->status,
            'comment' => 'Shipment status changed.',
            'changed_by_id' => auth()->id(),
        ]);
    }
}
