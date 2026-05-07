<?php

namespace App\Models;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\SupportTicketObserver;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'tenant_id',
    'store_id',
    'requester_id',
    'assigned_to_id',
    'ticket_number',
    'subject',
    'description',
    'category',
    'priority',
    'status',
    'resolution',
    'internal_notes',
    'last_response_at',
    'resolved_at',
    'closed_at',
    'metadata',
])]
#[ObservedBy([SupportTicketObserver::class])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $supportTicket): void {
            $supportTicket->ticket_number ??= self::nextTicketNumber();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SupportTicketCategory::class,
            'priority' => SupportTicketPriority::class,
            'status' => SupportTicketStatus::class,
            'last_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            SupportTicketStatus::Resolved->value,
            SupportTicketStatus::Closed->value,
        ]);
    }

    private static function nextTicketNumber(): string
    {
        do {
            $ticketNumber = 'SUP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (self::query()
            ->withoutGlobalScope('current_tenant')
            ->where('ticket_number', $ticketNumber)
            ->exists());

        return $ticketNumber;
    }
}
