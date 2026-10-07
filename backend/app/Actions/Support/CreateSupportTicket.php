<?php

namespace App\Actions\Support;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
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

        // Verify the requester is authorized to create tickets for the specified tenant
        $this->authorizeTenant($requester, $tenantId);

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

        // Temporarily set the current tenant so BelongsToTenant trait picks up the correct tenant_id
        $currentTenant = app(CurrentTenant::class);
        $previousTenant = $currentTenant->get();

        try {
            $currentTenant->set(Tenant::find($tenantId));

            // Build the ticket data without tenant_id (not fillable)
            $ticketData = [
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
            ];

            // Create model instance and explicitly set tenant_id before saving
            // This ensures the observer's saving event sees the correct tenant_id
            $ticket = new SupportTicket($ticketData);
            $ticket->setAttribute('tenant_id', $tenantId);
            $ticket->save();

            return $ticket;
        } finally {
            $currentTenant->set($previousTenant);
        }
    }

    /**
     * Verify the requester is authorized to create tickets for the specified tenant.
     */
    private function authorizeTenant(?User $requester, string $tenantId): void
    {
        if ($requester === null) {
            throw ValidationException::withMessages([
                'tenant_id' => __('Unable to determine requester for support ticket.'),
            ]);
        }

        // Super admins and platform support can create tickets for any tenant
        if ($requester->isSuperAdmin() || $requester->isPlatformSupport()) {
            return;
        }

        // For other users, verify they belong to the tenant and have permission
        $belongsToTenant = $requester->tenants()
            ->whereKey($tenantId)
            ->exists();

        if (! $belongsToTenant) {
            throw ValidationException::withMessages([
                'tenant_id' => __('You are not authorized to create support tickets for this tenant.'),
            ]);
        }

        // Verify the user has the permission to create support tickets in this tenant
        if (! $requester->hasTenantPermission($tenantId, \App\Enums\TenantPermission::SupportTicketsCreate->value)) {
            throw ValidationException::withMessages([
                'tenant_id' => __('You do not have permission to create support tickets for this tenant.'),
            ]);
        }
    }
}
