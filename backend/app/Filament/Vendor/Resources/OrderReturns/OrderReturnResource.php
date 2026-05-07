<?php

namespace App\Filament\Vendor\Resources\OrderReturns;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\OrderReturns\Pages\CreateOrderReturn;
use App\Filament\Vendor\Resources\OrderReturns\Pages\EditOrderReturn;
use App\Filament\Vendor\Resources\OrderReturns\Pages\ListOrderReturns;
use App\Filament\Vendor\Resources\OrderReturns\Schemas\OrderReturnForm;
use App\Filament\Vendor\Resources\OrderReturns\Tables\OrderReturnsTable;
use App\Models\OrderReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderReturnResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = OrderReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'return_number';

    public static function form(Schema $schema): Schema
    {
        return OrderReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderReturnsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrderReturns::route('/'),
            'create' => CreateOrderReturn::route('/create'),
            'edit' => EditOrderReturn::route('/{record}/edit'),
        ];
    }
}
