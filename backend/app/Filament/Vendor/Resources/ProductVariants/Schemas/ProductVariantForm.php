<?php

namespace App\Filament\Vendor\Resources\ProductVariants\Schemas;

use App\Enums\ProductStatus;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Select::make('product_id')
                    ->relationship(
                        'product',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => self::tenantScopedRelationshipQuery($query, 'name')
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(255),
                TextInput::make('option_signature')
                    ->placeholder('size=large;color=black')
                    ->helperText('Internal normalized unique key for now, for example: size=large;color=black.')
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : trim($state))
                    ->maxLength(255)
                    ->required(),
                TextInput::make('title')
                    ->maxLength(255),
                TextInput::make('price_minor')
                    ->helperText('Optional override. Leave empty to inherit the product price.')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('compare_at_price_minor')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('cost_price_minor')
                    ->numeric()
                    ->minValue(0),
                Select::make('status')
                    ->options(ProductStatus::class)
                    ->default(ProductStatus::Active->value)
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
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
