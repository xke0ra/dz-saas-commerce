<?php

namespace App\Observers;

use App\Enums\SupportTicketStatus;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class SupportTicketObserver
{
    public function saving(SupportTicket $supportTicket): void
    {
        $this->ensureStoreBelongsToTenant($supportTicket);

        if (! $supportTicket->isDirty('status')) {
            return;
        }

        if ($supportTicket->status === SupportTicketStatus::Resolved && $supportTicket->resolved_at === null) {
            $supportTicket->resolved_at = now();
        }

        if ($supportTicket->status === SupportTicketStatus::Closed && $supportTicket->closed_at === null) {
            $supportTicket->closed_at = now();
        }
    }

    public function created(SupportTicket $supportTicket): void
    {
        app(AuditLogger::class)->record(
            event: 'support_ticket.created',
            auditable: $supportTicket,
            newValues: [
                'ticket_number' => $supportTicket->ticket_number,
                'subject' => $supportTicket->subject,
                'status' => $supportTicket->status,
                'priority' => $supportTicket->priority,
            ],
        );
    }

    public function updated(SupportTicket $supportTicket): void
    {
        if ($supportTicket->wasChanged('status')) {
            app(AuditLogger::class)->record(
                event: 'support_ticket.status_changed',
                auditable: $supportTicket,
                oldValues: ['status' => $supportTicket->getOriginal('status')],
                newValues: ['status' => $supportTicket->status],
            );
        }

        if ($supportTicket->wasChanged('assigned_to_id')) {
            app(AuditLogger::class)->record(
                event: 'support_ticket.assigned',
                auditable: $supportTicket,
                oldValues: ['assigned_to_id' => $supportTicket->getOriginal('assigned_to_id')],
                newValues: ['assigned_to_id' => $supportTicket->assigned_to_id],
            );
        }
    }

    private function ensureStoreBelongsToTenant(SupportTicket $supportTicket): void
    {
        if ($supportTicket->store_id === null) {
            return;
        }

        $storeTenantId = Store::query()
            ->withoutGlobalScope('current_tenant')
            ->whereKey($supportTicket->store_id)
            ->value('tenant_id');

        if ($storeTenantId === $supportTicket->tenant_id) {
            return;
        }

        throw ValidationException::withMessages([
            'store_id' => __('The selected store does not belong to the selected tenant.'),
        ]);
    }
}
