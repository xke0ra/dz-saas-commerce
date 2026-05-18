<?php

namespace App\Filament\Vendor\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('slug')
                    ->maxLength(255)
                    ->alphaDash()
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(255),
                Textarea::make('short_description')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(ProductStatus::class)
                    ->default(ProductStatus::Draft->value)
                    ->required(),
                Select::make('type')
                    ->options(ProductType::class)
                    ->default(ProductType::Simple->value)
                    ->required()
                    ->helperText('Simple products are bought directly. Variable products require the customer to select a variant.'),
                TextInput::make('price_minor')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                TextInput::make('compare_at_price_minor')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('cost_price_minor')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('currency')
                    ->length(3)
                    ->required()
                    ->default('DZD'),
                Toggle::make('requires_shipping')
                    ->default(true)
                    ->required(),
                Toggle::make('is_featured')
                    ->default(false)
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                DateTimePicker::make('published_at'),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
