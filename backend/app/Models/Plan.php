<?php

namespace App\Models;

use App\Enums\PlanFeatureKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'price_minor', 'currency', 'billing_interval', 'is_active', 'sort_order', 'metadata'])]
class Plan extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function featureValue(PlanFeatureKey|string $key): mixed
    {
        $key = $key instanceof PlanFeatureKey ? $key->value : $key;

        $feature = $this->relationLoaded('features')
            ? $this->features->firstWhere('key', $key)
            : $this->features()->where('key', $key)->first();

        return $feature?->normalizedValue();
    }
}
