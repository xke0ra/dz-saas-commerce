<?php

namespace App\Filament\Vendor\Resources\ProductVariants;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\ProductVariants\Pages\CreateProductVariant;
use App\Filament\Vendor\Resources\ProductVariants\Pages\EditProductVariant;
use App\Filament\Vendor\Resources\ProductVariants\Pages\ListProductVariants;
use App\Filament\Vendor\Resources\ProductVariants\Schemas\ProductVariantForm;
use App\Filament\Vendor\Resources\ProductVariants\Tables\ProductVariantsTable;
use App\Models\ProductVariant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductVariantResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = ProductVariant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'option_signature';

    public static function form(Schema $schema): Schema
    {
        return ProductVariantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductVariantsTable::configure($table);
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
            'index' => ListProductVariants::route('/'),
            'create' => CreateProductVariant::route('/create'),
            'edit' => EditProductVariant::route('/{record}/edit'),
        ];
    }
}
