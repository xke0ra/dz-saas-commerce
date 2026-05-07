<?php

namespace App\Filament\Vendor\Resources\Shipments;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\Shipments\Pages\CreateShipment;
use App\Filament\Vendor\Resources\Shipments\Pages\EditShipment;
use App\Filament\Vendor\Resources\Shipments\Pages\ListShipments;
use App\Filament\Vendor\Resources\Shipments\Schemas\ShipmentForm;
use App\Filament\Vendor\Resources\Shipments\Tables\ShipmentsTable;
use App\Models\Shipment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShipmentResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = Shipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'tracking_number';

    public static function form(Schema $schema): Schema
    {
        return ShipmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShipmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShipments::route('/'),
            'create' => CreateShipment::route('/create'),
            'edit' => EditShipment::route('/{record}/edit'),
        ];
    }
}
