<?php

namespace App\Support\Catalog;

use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class ProductVariantOptionValueValidator
{
    public function validate(string $tenantId, string $productVariantId, string $productOptionValueId): void
    {
        $variant = ProductVariant::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->whereKey($productVariantId)
            ->first();

        if ($variant === null) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'The selected product variant is not available for this tenant.',
            ]);
        }

        $optionValue = ProductOptionValue::query()
            ->withoutGlobalScope('current_tenant')
            ->with('option')
            ->where('tenant_id', $tenantId)
            ->whereKey($productOptionValueId)
            ->first();

        if ($optionValue === null) {
            throw ValidationException::withMessages([
                'product_option_value_id' => 'The selected option value is not available for this tenant.',
            ]);
        }

        if ($optionValue->option?->product_id !== $variant->product_id) {
            throw ValidationException::withMessages([
                'product_option_value_id' => 'The selected option value must belong to the same product as the variant.',
            ]);
        }
    }
}
