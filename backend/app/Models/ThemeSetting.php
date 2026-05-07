<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ThemeSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'store_id',
    'theme_name',
    'primary_color',
    'accent_color',
    'background_color',
    'foreground_color',
    'heading_font',
    'body_font',
    'logo_path',
    'favicon_path',
    'hero_image_path',
    'hero_title',
    'hero_subtitle',
    'product_card_style',
    'layout_settings',
    'is_active',
])]
class ThemeSetting extends Model
{
    /** @use HasFactory<ThemeSettingFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layout_settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
