<?php

namespace App\Observers;

use App\Models\ProductImage;

class ProductImageObserver
{
    public function saving(ProductImage $productImage): void
    {
        if ($productImage->tenant_id === null && $productImage->product !== null) {
            $productImage->tenant_id = $productImage->product->tenant_id;
        }

        if (! $productImage->is_primary) {
            return;
        }

        ProductImage::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $productImage->tenant_id)
            ->where('product_id', $productImage->product_id)
            ->when($productImage->exists, fn ($query) => $query->whereKeyNot($productImage->id))
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
