<?php

namespace App\Filament\Vendor\Resources\ProductVariantOptionValues\Schemas;

use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductVariantOptionValueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Select::make('product_variant_id')
                    ->relationship(
                        'variant',
                        'option_signature',
                        modifyQueryUsing: fn (Builder $query): Builder => self::tenantScopedRelationshipQuery($query, 'option_signature')
                    )
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('product_option_value_id', null);
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('product_option_value_id')
                    ->options(fn (Get $get): array => self::optionValueOptions($get('product_variant_id')))
                    ->helperText('Only option values for the selected variant product are available.')
                    ->disabled(fn (Get $get): bool => blank($get('product_variant_id')))
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }

    private static function tenantScopedRelationshipQuery(Builder $query, string $orderColumn): Builder
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->withoutGlobalScope('current_tenant')
            ->where($query->getModel()->qualifyColumn('tenant_id'), $tenantId)
            ->orderBy($orderColumn);
    }

    /**
     * @return array<string, string>
     */
    public static function optionValueOptions(mixed $productVariantId): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null || ! is_string($productVariantId) || $productVariantId === '') {
            return [];
        }

        $variant = ProductVariant::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->whereKey($productVariantId)
            ->first();

        if ($variant === null) {
            return [];
        }

        return ProductOptionValue::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->whereHas('option', function (Builder $query) use ($tenantId, $variant): void {
                $query
                    ->withoutGlobalScope('current_tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $variant->product_id);
            })
            ->with('option')
            ->orderBy('position')
            ->orderBy('value')
            ->get()
            ->mapWithKeys(fn (ProductOptionValue $value): array => [
                $value->id => trim(($value->option?->name ?? 'Option').' / '.$value->value),
            ])
            ->all();
    }
}
