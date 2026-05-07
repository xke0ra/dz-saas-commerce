<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Audit\AuditLogger;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $fromStatus = OrderStatus::tryFrom((string) $order->getRawOriginal('status'));

        $order->statusHistories()->create([
            'tenant_id' => $order->tenant_id,
            'from_status' => $fromStatus,
            'to_status' => $order->status,
            'comment' => 'Order status changed.',
            'changed_by_id' => auth()->id(),
        ]);

        app(AuditLogger::class)->record(
            event: 'order.status_changed',
            auditable: $order,
            oldValues: [
                'status' => $fromStatus,
            ],
            newValues: [
                'status' => $order->status,
            ],
        );
    }
}
