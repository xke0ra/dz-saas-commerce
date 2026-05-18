<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'product_id',
    'name',
    'position',
])]
class ProductOption extends Model
{
    /** @use HasFactory<ProductOptionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ProductOptionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @param  Builder<ProductOption>  $query
     * @return Builder<ProductOption>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
