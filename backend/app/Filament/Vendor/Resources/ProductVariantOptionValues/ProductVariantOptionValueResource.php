<?php

namespace App\Filament\Vendor\Resources\ProductVariantOptionValues;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\ProductVariantOptionValues\Pages\CreateProductVariantOptionValue;
use App\Filament\Vendor\Resources\ProductVariantOptionValues\Pages\EditProductVariantOptionValue;
use App\Filament\Vendor\Resources\ProductVariantOptionValues\Pages\ListProductVariantOptionValues;
use App\Filament\Vendor\Resources\ProductVariantOptionValues\Schemas\ProductVariantOptionValueForm;
use App\Filament\Vendor\Resources\ProductVariantOptionValues\Tables\ProductVariantOptionValuesTable;
use App\Models\ProductVariantOptionValue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductVariantOptionValueResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = ProductVariantOptionValue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'product_option_value_id';

    public static function form(Schema $schema): Schema
    {
        return ProductVariantOptionValueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductVariantOptionValuesTable::configure($table);
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
            'index' => ListProductVariantOptionValues::route('/'),
            'create' => CreateProductVariantOptionValue::route('/create'),
            'edit' => EditProductVariantOptionValue::route('/{record}/edit'),
        ];
    }
}
