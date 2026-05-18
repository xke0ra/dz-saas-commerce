<?php

namespace App\Filament\Vendor\Resources\ProductOptionValues;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\ProductOptionValues\Pages\CreateProductOptionValue;
use App\Filament\Vendor\Resources\ProductOptionValues\Pages\EditProductOptionValue;
use App\Filament\Vendor\Resources\ProductOptionValues\Pages\ListProductOptionValues;
use App\Filament\Vendor\Resources\ProductOptionValues\Schemas\ProductOptionValueForm;
use App\Filament\Vendor\Resources\ProductOptionValues\Tables\ProductOptionValuesTable;
use App\Models\ProductOptionValue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductOptionValueResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = ProductOptionValue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'value';

    public static function form(Schema $schema): Schema
    {
        return ProductOptionValueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductOptionValuesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductOptionValues::route('/'),
            'create' => CreateProductOptionValue::route('/create'),
            'edit' => EditProductOptionValue::route('/{record}/edit'),
        ];
    }
}
