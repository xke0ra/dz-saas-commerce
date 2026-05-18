<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'product_id',
    'sku',
    'option_signature',
    'title',
    'price_minor',
    'compare_at_price_minor',
    'cost_price_minor',
    'status',
    'sort_order',
    'metadata',
])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'status' => ProductStatus::class,
            'price_minor' => 'integer',
            'compare_at_price_minor' => 'integer',
            'cost_price_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsToMany<ProductOptionValue, $this>
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_option_values',
            'product_variant_id',
            'product_option_value_id'
        )->withPivot(['id', 'tenant_id', 'created_at']);
    }

    /**
     * @return HasMany<InventoryItem, $this>
     */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function effectivePriceMinor(): int
    {
        if ($this->price_minor !== null) {
            return $this->price_minor;
        }

        return (int) $this->product?->price_minor;
    }
}
