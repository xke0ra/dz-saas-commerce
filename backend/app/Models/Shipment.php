<?php

namespace App\Models;

use App\Enums\DeliveryType;
use App\Enums\ShipmentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\ShipmentObserver;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'order_id',
    'shipping_company_id',
    'failed_delivery_reason_id',
    'tracking_number',
    'status',
    'delivery_type',
    'wilaya_id',
    'commune_id',
    'destination_address',
    'shipping_fee_minor',
    'currency',
    'shipped_at',
    'delivered_at',
    'failure_note',
    'metadata',
])]
#[ObservedBy([ShipmentObserver::class])]
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'delivery_type' => DeliveryType::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<ShippingCompany, $this>
     */
    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    /**
     * @return BelongsTo<FailedDeliveryReason, $this>
     */
    public function failedDeliveryReason(): BelongsTo
    {
        return $this->belongsTo(FailedDeliveryReason::class);
    }

    /**
     * @return BelongsTo<Wilaya, $this>
     */
    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * @return HasMany<ShipmentStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class)->latest();
    }

    public function trackingUrl(): ?string
    {
        if ($this->tracking_number === null || $this->tracking_number === '') {
            return null;
        }

        $template = $this->shippingCompany?->tracking_url_template;

        if ($template === null || $template === '') {
            return null;
        }

        return str_replace('{tracking_number}', urlencode($this->tracking_number), $template);
    }
}
