<?php

namespace App\Filament\Vendor\Resources\ProductVariantOptionValues\Schemas;

use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
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
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('product_option_value_id')
                    ->relationship(
                        'optionValue',
                        'value',
                        modifyQueryUsing: fn (Builder $query): Builder => self::tenantScopedRelationshipQuery($query, 'value')
                    )
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
}
