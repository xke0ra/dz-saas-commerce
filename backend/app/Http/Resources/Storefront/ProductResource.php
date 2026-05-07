<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'status' => $this->status?->value,
            'price_minor' => $this->price_minor,
            'compare_at_price_minor' => $this->compare_at_price_minor,
            'currency' => $this->currency,
            'requires_shipping' => $this->requires_shipping,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toISOString(),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'inventory' => $this->whenLoaded('inventoryItem', fn (): ?array => $this->inventoryItem ? [
                'track_quantity' => $this->inventoryItem->track_quantity,
                'available_quantity' => $this->inventoryItem->availableQuantity(),
                'allow_backorders' => $this->inventoryItem->allow_backorders,
            ] : null),
        ];
    }
}
