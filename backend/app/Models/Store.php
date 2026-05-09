<?php

namespace App\Models;

use App\Enums\StoreStatus;
use App\Observers\StoreObserver;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id', 'name', 'slug', 'domain', 'subdomain', 'status', 'locale', 'currency', 'settings'])]
#[ObservedBy([StoreObserver::class])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'status' => StoreStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<SupportTicket, $this>
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * @return HasOne<StoreSetting, $this>
     */
    public function storeSetting(): HasOne
    {
        return $this->hasOne(StoreSetting::class);
    }

    /**
     * @return HasOne<ThemeSetting, $this>
     */
    public function themeSetting(): HasOne
    {
        return $this->hasOne(ThemeSetting::class)->where('is_active', true);
    }

    /**
     * @param  Builder<Store>  $query
     * @return Builder<Store>
     */
    public function scopeForTenant(Builder $query, Tenant|string|null $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        if ($tenantId === null || $tenantId === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->getModel()->qualifyColumn('tenant_id'), $tenantId);
    }
}
