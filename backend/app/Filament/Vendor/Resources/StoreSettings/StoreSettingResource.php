<?php

namespace App\Filament\Vendor\Resources\StoreSettings;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\StoreSettings\Pages\CreateStoreSetting;
use App\Filament\Vendor\Resources\StoreSettings\Pages\EditStoreSetting;
use App\Filament\Vendor\Resources\StoreSettings\Pages\ListStoreSettings;
use App\Filament\Vendor\Resources\StoreSettings\Schemas\StoreSettingForm;
use App\Filament\Vendor\Resources\StoreSettings\Tables\StoreSettingsTable;
use App\Models\StoreSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StoreSettingResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = StoreSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Store settings';

    protected static ?string $modelLabel = 'store setting';

    protected static ?string $pluralModelLabel = 'store settings';

    public static function form(Schema $schema): Schema
    {
        return StoreSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreSettings::route('/'),
            'create' => CreateStoreSetting::route('/create'),
            'edit' => EditStoreSetting::route('/{record}/edit'),
        ];
    }
}
