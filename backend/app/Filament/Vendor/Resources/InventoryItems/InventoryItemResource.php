<?php

namespace App\Filament\Vendor\Resources\InventoryItems;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Vendor\Resources\InventoryItems\Pages\EditInventoryItem;
use App\Filament\Vendor\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Filament\Vendor\Resources\InventoryItems\Schemas\InventoryItemForm;
use App\Filament\Vendor\Resources\InventoryItems\Tables\InventoryItemsTable;
use App\Models\InventoryItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventoryItemResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = InventoryItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'sku';

    public static function form(Schema $schema): Schema
    {
        return InventoryItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryItemsTable::configure($table);
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
            'index' => ListInventoryItems::route('/'),
            'create' => CreateInventoryItem::route('/create'),
            'edit' => EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
