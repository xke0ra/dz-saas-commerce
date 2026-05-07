<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class SearchStorefrontProducts
{
    public function handle(Store $store, string $term, int $perPage = 24): LengthAwarePaginator
    {
        $term = trim($term);
        $perPage = max(1, min($perPage, 48));

        if ($this->usesExternalSearch()) {
            try {
                return $this->searchWithScout($store, $term, $perPage);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $this->searchWithDatabase($store, $term, $perPage);
    }

    private function searchWithScout(Store $store, string $term, int $perPage): LengthAwarePaginator
    {
        return Product::search($term)
            ->where('tenant_id', $store->tenant_id)
            ->where('status', ProductStatus::Active->value)
            ->where('published_at_timestamp', '<=', now()->timestamp)
            ->query(fn (Builder $query): Builder => $query
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $store->tenant_id)
                ->visibleOnStorefront()
                ->with(['category', 'images', 'inventoryItem']))
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at_timestamp')
            ->paginate($perPage);
    }

    private function searchWithDatabase(Store $store, string $term, int $perPage): LengthAwarePaginator
    {
        $term = mb_strtolower($term);

        return Product::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id)
            ->visibleOnStorefront()
            ->where(function (Builder $query) use ($term): void {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(COALESCE(short_description, \'\')) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', ["%{$term}%"])
                    ->orWhereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery
                        ->withoutGlobalScope('current_tenant')
                        ->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]));
            })
            ->with(['category', 'images', 'inventoryItem'])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function usesExternalSearch(): bool
    {
        return config('scout.driver') === 'meilisearch';
    }
}
