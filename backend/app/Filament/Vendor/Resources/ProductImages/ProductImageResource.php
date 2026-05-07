<?php

namespace App\Filament\Vendor\Resources\ProductImages;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\ProductImages\Pages\CreateProductImage;
use App\Filament\Vendor\Resources\ProductImages\Pages\EditProductImage;
use App\Filament\Vendor\Resources\ProductImages\Pages\ListProductImages;
use App\Filament\Vendor\Resources\ProductImages\Schemas\ProductImageForm;
use App\Filament\Vendor\Resources\ProductImages\Tables\ProductImagesTable;
use App\Models\ProductImage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductImageResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = ProductImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'path';

    public static function form(Schema $schema): Schema
    {
        return ProductImageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductImagesTable::configure($table);
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
            'index' => ListProductImages::route('/'),
            'create' => CreateProductImage::route('/create'),
            'edit' => EditProductImage::route('/{record}/edit'),
        ];
    }
}
