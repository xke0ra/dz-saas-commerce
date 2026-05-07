<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ShippingCompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'name',
    'code',
    'contact_phone',
    'tracking_url_template',
    'supports_home_delivery',
    'supports_desk_delivery',
    'is_active',
    'settings',
])]
class ShippingCompany extends Model
{
    /** @use HasFactory<ShippingCompanyFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supports_home_delivery' => 'boolean',
            'supports_desk_delivery' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
