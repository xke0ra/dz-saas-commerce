<?php

namespace Database\Factories;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->for($tenant)->create();

        return [
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'requester_id' => User::factory(),
            'assigned_to_id' => null,
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'category' => SupportTicketCategory::General,
            'priority' => SupportTicketPriority::Normal,
            'status' => SupportTicketStatus::Open,
            'resolution' => null,
            'internal_notes' => null,
            'last_response_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'metadata' => [],
        ];
    }

    public function forTenant(Tenant $tenant, ?Store $store = null): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
            'store_id' => $store?->id,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
