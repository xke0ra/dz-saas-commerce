<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'description', 'is_enabled', 'metadata'])]
class FeatureFlag extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @param  Builder<FeatureFlag>  $query
     * @return Builder<FeatureFlag>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public static function enabled(string $key): bool
    {
        return self::query()
            ->where('key', $key)
            ->where('is_enabled', true)
            ->exists();
    }
}
