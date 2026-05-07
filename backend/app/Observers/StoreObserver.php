<?php

namespace App\Observers;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Support\Audit\AuditLogger;

class StoreObserver
{
    public function updated(Store $store): void
    {
        if (! $store->wasChanged('status')) {
            return;
        }

        $fromStatus = StoreStatus::tryFrom((string) $store->getRawOriginal('status'));

        app(AuditLogger::class)->record(
            event: $store->status === StoreStatus::Suspended ? 'store.suspended' : 'store.status_changed',
            auditable: $store,
            oldValues: [
                'status' => $fromStatus,
            ],
            newValues: [
                'status' => $store->status,
            ],
        );
    }
}
