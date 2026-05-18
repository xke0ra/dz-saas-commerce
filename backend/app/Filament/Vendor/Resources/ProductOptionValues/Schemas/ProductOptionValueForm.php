<?php

namespace App\Filament\Vendor\Resources\ProductOptionValues\Schemas;

use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductOptionValueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Select::make('product_option_id')
                    ->relationship(
                        'option',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => self::tenantScopedRelationshipQuery($query, 'name')
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('value')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('position')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
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
