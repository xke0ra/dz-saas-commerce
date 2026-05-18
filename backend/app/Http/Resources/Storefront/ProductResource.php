<?php

namespace App\Http\Resources\Storefront;

use App\Enums\ProductStatus;
use App\Models\InventoryItem;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

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
            'variants' => $this->whenLoaded('variants', fn (): array => $this->storefrontVariants()),
            'options' => $this->whenLoaded('options', fn (): array => $this->storefrontOptions()),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storefrontVariants(): array
    {
        return $this->visibleVariants()
            ->map(function (ProductVariant $variant): array {
                $availability = $this->variantAvailability($variant);

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'title' => $variant->title,
                    'option_signature' => $variant->option_signature,
                    'price_minor' => $variant->price_minor,
                    'compare_at_price_minor' => $variant->compare_at_price_minor,
                    'effective_price_minor' => (int) ($variant->price_minor ?? $this->price_minor),
                    'status' => $variant->status?->value,
                    'sort_order' => $variant->sort_order,
                    'available_quantity' => $availability['available_quantity'],
                    'is_available' => $availability['is_available'],
                    'selected_options' => $this->selectedOptions($variant),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storefrontOptions(): array
    {
        if (! $this->relationLoaded('options')) {
            return [];
        }

        $visibleValueIds = $this->visibleOptionValueIds();

        if ($visibleValueIds === []) {
            return [];
        }

        return $this->options
            ->map(function (ProductOption $option) use ($visibleValueIds): ?array {
                $values = $option->relationLoaded('values') ? $option->values : collect();
                $values = $values
                    ->filter(fn (ProductOptionValue $value): bool => isset($visibleValueIds[$value->id]))
                    ->map(fn (ProductOptionValue $value): array => [
                        'id' => $value->id,
                        'value' => $value->value,
                        'position' => $value->position,
                    ])
                    ->values();

                if ($values->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $option->id,
                    'name' => $option->name,
                    'position' => $option->position,
                    'values' => $values->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function visibleVariants(): Collection
    {
        if (! $this->relationLoaded('variants')) {
            return collect();
        }

        return $this->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->status === ProductStatus::Active)
            ->values();
    }

    /**
     * @return array<string, bool>
     */
    private function visibleOptionValueIds(): array
    {
        $valueIds = [];

        foreach ($this->visibleVariants() as $variant) {
            if (! $variant->relationLoaded('optionValues')) {
                continue;
            }

            foreach ($variant->optionValues as $value) {
                $valueIds[$value->id] = true;
            }
        }

        return $valueIds;
    }

    /**
     * @return array<string, string>
     */
    private function selectedOptions(ProductVariant $variant): array
    {
        if (! $variant->relationLoaded('optionValues')) {
            return [];
        }

        return $variant->optionValues
            ->filter(fn (ProductOptionValue $value): bool => $value->relationLoaded('option')
                && $value->option !== null
                && $value->option->product_id === $this->id)
            ->sortBy(fn (ProductOptionValue $value): string => sprintf(
                '%010d|%s|%010d|%s',
                (int) $value->option->position,
                $value->option->name,
                (int) $value->position,
                $value->value,
            ))
            ->mapWithKeys(fn (ProductOptionValue $value): array => [
                $value->option->name => $value->value,
            ])
            ->all();
    }

    /**
     * @return array{available_quantity: int|null, is_available: bool}
     */
    private function variantAvailability(ProductVariant $variant): array
    {
        $inventoryItem = $this->variantInventory($variant);

        if ($inventoryItem === null) {
            return [
                'available_quantity' => 0,
                'is_available' => false,
            ];
        }

        if (! $inventoryItem->track_quantity) {
            return [
                'available_quantity' => null,
                'is_available' => true,
            ];
        }

        $availableQuantity = $inventoryItem->availableQuantity();

        return [
            'available_quantity' => $availableQuantity,
            'is_available' => $inventoryItem->allow_backorders || $availableQuantity > 0,
        ];
    }

    private function variantInventory(ProductVariant $variant): ?InventoryItem
    {
        if (! $variant->relationLoaded('inventoryItems')) {
            return null;
        }

        return $variant->inventoryItems
            ->first(fn (InventoryItem $inventoryItem): bool => $inventoryItem->tenant_id === $this->tenant_id
                && $inventoryItem->product_id === $this->id
                && $inventoryItem->product_variant_id === $variant->id);
    }
}
