<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Inventory\StockMovementRequestHasher;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JsonException;

#[Fillable(['product_id',
    'product_variant_id',
    'inventory_item_id',
    'order_id',
    'order_item_id',
    'order_return_id',
    'actor_id',
    'type',
    'quantity_delta',
    'reserved_delta',
    'balance_quantity_after',
    'balance_reserved_after',
    'reason',
    'metadata',
    'occurred_at',
    'request_hash',
])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
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
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<OrderReturn, $this>
     */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Generate a deterministic SHA-256 request hash for stock movement deduplication.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public static function generateRequestHash(array $payload): string
    {
        $hasher = new StockMovementRequestHasher();

        return $hasher->hash($payload);
    }

    /**
     * Build the payload array for generating a stock movement request hash.
     * This includes all fields that uniquely identify a stock movement request.
     *
     * @return array<string, mixed>
     */
    public static function buildRequestHashPayload(
        string $tenantId,
        string $productId,
        string $inventoryItemId,
        StockMovementType $type,
        ?string $productVariantId = null,
        ?string $orderId = null,
        ?string $orderItemId = null,
        ?string $orderReturnId = null,
        ?int $actorId = null,
        int $quantityDelta = 0,
        int $reservedDelta = 0,
        string $reason = '',
    ): array {
        return [
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'inventory_item_id' => $inventoryItemId,
            'product_variant_id' => $productVariantId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'order_return_id' => $orderReturnId,
            'actor_id' => $actorId,
            'type' => $type->value,
            'quantity_delta' => $quantityDelta,
            'reserved_delta' => $reservedDelta,
            'reason' => trim($reason),
        ];
    }
}
