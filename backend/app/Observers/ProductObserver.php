<?php

namespace App\Observers;

use App\Models\Product;
use App\Support\Audit\AuditLogger;

class ProductObserver
{
    public function deleted(Product $product): void
    {
        app(AuditLogger::class)->record(
            event: 'product.deleted',
            auditable: $product,
            oldValues: [
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'status' => $product->status,
            ],
        );
    }
}
