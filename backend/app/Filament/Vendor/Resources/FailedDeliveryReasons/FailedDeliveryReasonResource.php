<?php

namespace App\Filament\Vendor\Resources\FailedDeliveryReasons;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\FailedDeliveryReasons\Pages\CreateFailedDeliveryReason;
use App\Filament\Vendor\Resources\FailedDeliveryReasons\Pages\EditFailedDeliveryReason;
use App\Filament\Vendor\Resources\FailedDeliveryReasons\Pages\ListFailedDeliveryReasons;
use App\Filament\Vendor\Resources\FailedDeliveryReasons\Schemas\FailedDeliveryReasonForm;
use App\Filament\Vendor\Resources\FailedDeliveryReasons\Tables\FailedDeliveryReasonsTable;
use App\Models\FailedDeliveryReason;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FailedDeliveryReasonResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = FailedDeliveryReason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'label_fr';

    public static function form(Schema $schema): Schema
    {
        return FailedDeliveryReasonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FailedDeliveryReasonsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFailedDeliveryReasons::route('/'),
            'create' => CreateFailedDeliveryReason::route('/create'),
            'edit' => EditFailedDeliveryReason::route('/{record}/edit'),
        ];
    }
}
