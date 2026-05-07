<?php

namespace App\Filament\Vendor\Resources\ShippingRates\Schemas;

use App\Enums\DeliveryType;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShippingRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Select::make('wilaya_id')
                    ->relationship('wilaya', 'name_fr')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('commune_id')
                    ->relationship('commune', 'name_fr')
                    ->searchable()
                    ->preload(),
                Select::make('delivery_type')
                    ->options(DeliveryType::class)
                    ->default(DeliveryType::Home->value)
                    ->required(),
                TextInput::make('price_minor')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                TextInput::make('currency')
                    ->length(3)
                    ->required()
                    ->default('DZD'),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
