<?php

namespace App\Actions\Support;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateSupportTicket
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $requester = null): SupportTicket
    {
        $tenantId = $data['tenant_id'] ?? null;

        if (! is_string($tenantId) || $tenantId === '') {
            throw ValidationException::withMessages([
                'tenant_id' => __('A tenant is required for support tickets.'),
            ]);
        }

        $storeId = $data['store_id'] ?? null;

        if (is_string($storeId) && $storeId !== '') {
            $storeBelongsToTenant = Store::query()
                ->withoutGlobalScope('current_tenant')
                ->whereKey($storeId)
                ->where('tenant_id', $tenantId)
                ->exists();

            if (! $storeBelongsToTenant) {
                throw ValidationException::withMessages([
                    'store_id' => __('The selected store does not belong to the selected tenant.'),
                ]);
            }
        }

        return SupportTicket::query()
            ->withoutGlobalScope('current_tenant')
            ->create([
                'tenant_id' => $tenantId,
                'store_id' => $storeId ?: null,
                'requester_id' => $data['requester_id'] ?? $requester?->getKey(),
                'assigned_to_id' => $data['assigned_to_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'category' => SupportTicketCategory::from($data['category'] ?? SupportTicketCategory::General->value),
                'priority' => SupportTicketPriority::from($data['priority'] ?? SupportTicketPriority::Normal->value),
                'status' => SupportTicketStatus::from($data['status'] ?? SupportTicketStatus::Open->value),
                'resolution' => $data['resolution'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);
    }
}
