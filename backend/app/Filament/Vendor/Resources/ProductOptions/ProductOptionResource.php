<?php

namespace App\Filament\Vendor\Resources\ProductOptions;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\ProductOptions\Pages\CreateProductOption;
use App\Filament\Vendor\Resources\ProductOptions\Pages\EditProductOption;
use App\Filament\Vendor\Resources\ProductOptions\Pages\ListProductOptions;
use App\Filament\Vendor\Resources\ProductOptions\Schemas\ProductOptionForm;
use App\Filament\Vendor\Resources\ProductOptions\Tables\ProductOptionsTable;
use App\Models\ProductOption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductOptionResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = ProductOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductOptionsTable::configure($table);
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
            'index' => ListProductOptions::route('/'),
            'create' => CreateProductOption::route('/create'),
            'edit' => EditProductOption::route('/{record}/edit'),
        ];
    }
}
