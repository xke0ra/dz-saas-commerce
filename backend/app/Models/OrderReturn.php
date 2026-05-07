<?php

namespace App\Models;

use App\Enums\OrderReturnStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\OrderReturnObserver;
use Database\Factories\OrderReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'order_id',
    'customer_id',
    'return_number',
    'status',
    'reason',
    'resolution_note',
    'requested_at',
    'resolved_at',
    'metadata',
])]
#[ObservedBy([OrderReturnObserver::class])]
class OrderReturn extends Model
{
    /** @use HasFactory<OrderReturnFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderReturnStatus::class,
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
