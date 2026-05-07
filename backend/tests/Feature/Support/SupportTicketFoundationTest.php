<?php

use App\Actions\Support\CreateSupportTicket;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Filament\Vendor\Resources\SupportTickets\SupportTicketResource as VendorSupportTicketResource;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

it('creates tenant scoped support tickets and records audit logs', function (): void {
    $requester = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create();

    $tenant->users()->attach($requester, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);

    $this->actingAs($requester);

    $ticket = app(CreateSupportTicket::class)->handle([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'subject' => 'Checkout phone confirmation issue',
        'description' => 'Customers cannot submit quick orders from Algiers.',
        'category' => SupportTicketCategory::Storefront->value,
        'priority' => SupportTicketPriority::High->value,
    ], $requester);

    expect($ticket->tenant_id)->toBe($tenant->id)
        ->and($ticket->store_id)->toBe($store->id)
        ->and($ticket->requester_id)->toBe($requester->id)
        ->and($ticket->ticket_number)->toStartWith('SUP-')
        ->and($ticket->status)->toBe(SupportTicketStatus::Open);

    expect(AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event', 'support_ticket.created')
        ->where('auditable_id', $ticket->id)
        ->exists())->toBeTrue();
});

it('rejects assigning a support ticket to a store from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $otherStore = Store::factory()->for($otherTenant)->create();

    app(CreateSupportTicket::class)->handle([
        'tenant_id' => $tenant->id,
        'store_id' => $otherStore->id,
        'subject' => 'Wrong store',
        'description' => 'This should fail tenant integrity validation.',
    ]);
})->throws(ValidationException::class);

it('scopes vendor support ticket resources to the current tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $ticket = SupportTicket::factory()->forTenant($tenant)->create();
    SupportTicket::factory()->forTenant($otherTenant)->create();

    withSupportTicketTenant($tenant, function () use ($ticket): void {
        expect(VendorSupportTicketResource::getEloquentQuery()->pluck('id')->all())->toBe([$ticket->id]);
    });
});

it('protects support tickets with tenant permissions and platform support access', function (): void {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $support = User::factory()->platformSupport()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $ticket = SupportTicket::factory()->forTenant($tenant)->create();

    $tenant->users()->attach($owner, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);
    $tenant->users()->attach($staff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    withSupportTicketTenant($tenant, function () use ($owner, $staff, $support, $superAdmin, $ticket): void {
        expect($owner->can('viewAny', SupportTicket::class))->toBeTrue()
            ->and($owner->can('update', $ticket))->toBeTrue()
            ->and($staff->can('viewAny', SupportTicket::class))->toBeTrue()
            ->and($staff->can('create', SupportTicket::class))->toBeTrue()
            ->and($staff->can('update', $ticket))->toBeFalse()
            ->and($support->can('viewAny', SupportTicket::class))->toBeTrue()
            ->and($support->can('update', $ticket))->toBeTrue()
            ->and($superAdmin->can('delete', $ticket))->toBeTrue();
    });

    $tenant->users()->updateExistingPivot($staff->id, [
        'permissions' => json_encode([
            TenantPermission::SupportTicketsUpdate->value => true,
        ]),
    ]);

    withSupportTicketTenant($tenant, function () use ($staff, $ticket): void {
        expect($staff->fresh()->can('update', $ticket))->toBeTrue();
    });
});

it('allows platform support users to access only the support panel', function (): void {
    $support = User::factory()->platformSupport()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect($support->canAccessPanel(Filament::getPanel('support')))->toBeTrue()
        ->and($support->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($support->canAccessPanel(Filament::getPanel('vendor')))->toBeFalse()
        ->and($superAdmin->canAccessPanel(Filament::getPanel('support')))->toBeTrue()
        ->and($superAdmin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

function withSupportTicketTenant(Tenant $tenant, Closure $callback): void
{
    $currentTenant = app(CurrentTenant::class);
    $currentTenant->set($tenant);

    try {
        $callback();
    } finally {
        $currentTenant->forget();
    }
}
