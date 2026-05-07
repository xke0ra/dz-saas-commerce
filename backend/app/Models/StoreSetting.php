<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StoreSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'store_id',
    'seller_name',
    'seller_address',
    'commercial_registration_number',
    'tax_identification_number',
    'public_email',
    'public_phone',
    'support_phone',
    'whatsapp_phone',
    'seo_title',
    'seo_description',
    'announcement_text',
    'terms_content',
    'privacy_content',
    'return_policy_content',
    'shipping_policy_content',
    'social_links',
    'metadata',
])]
class StoreSetting extends Model
{
    /** @use HasFactory<StoreSettingFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'social_links' => 'array',
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

    public function legalContent(string $page): ?string
    {
        return match ($page) {
            'terms' => $this->terms_content,
            'privacy' => $this->privacy_content,
            'returns' => $this->return_policy_content,
            'shipping' => $this->shipping_policy_content,
            default => null,
        };
    }
}
