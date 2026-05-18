<?php

namespace App\Filament\Vendor\Resources\ProductVariantOptionValues\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductVariantOptionValues\ProductVariantOptionValueResource;
use App\Support\Catalog\ProductVariantOptionValueValidator;
use App\Support\Tenancy\CurrentTenant;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditProductVariantOptionValue extends EditRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductVariantOptionValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }

        $tenantId = $data['tenant_id'] ?? null;

        if (! is_string($tenantId) || $tenantId === '') {
            throw ValidationException::withMessages([
                'tenant_id' => 'A tenant is required before linking a variant to an option value.',
            ]);
        }

        app(ProductVariantOptionValueValidator::class)->validate(
            tenantId: $tenantId,
            productVariantId: (string) $data['product_variant_id'],
            productOptionValueId: (string) $data['product_option_value_id'],
        );

        return $data;
    }
}
