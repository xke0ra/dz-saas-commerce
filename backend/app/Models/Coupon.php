<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'code',
    'name',
    'type',
    'value',
    'max_discount_minor',
    'minimum_subtotal_minor',
    'usage_limit',
    'used_count',
    'starts_at',
    'ends_at',
    'is_active',
    'metadata',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => str($value)->trim()->upper()->toString(),
        );
    }

    public function calculateDiscount(int $subtotalMinor): int
    {
        if ($subtotalMinor <= 0) {
            return 0;
        }

        $discount = match ($this->type) {
            CouponType::FixedAmount => $this->value,
            CouponType::Percentage => intdiv($subtotalMinor * $this->value, 100),
        };

        if ($this->max_discount_minor !== null) {
            $discount = min($discount, $this->max_discount_minor);
        }

        return min($discount, $subtotalMinor);
    }

    public function hasAvailableUsage(): bool
    {
        return $this->usage_limit === null || $this->used_count < $this->usage_limit;
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
