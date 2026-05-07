<?php

namespace App\Filament\Vendor\Resources\ThemeSettings;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\ThemeSettings\Pages\CreateThemeSetting;
use App\Filament\Vendor\Resources\ThemeSettings\Pages\EditThemeSetting;
use App\Filament\Vendor\Resources\ThemeSettings\Pages\ListThemeSettings;
use App\Filament\Vendor\Resources\ThemeSettings\Schemas\ThemeSettingForm;
use App\Filament\Vendor\Resources\ThemeSettings\Tables\ThemeSettingsTable;
use App\Models\ThemeSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ThemeSettingResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = ThemeSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Themes';

    protected static ?string $modelLabel = 'theme setting';

    protected static ?string $pluralModelLabel = 'theme settings';

    public static function form(Schema $schema): Schema
    {
        return ThemeSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ThemeSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThemeSettings::route('/'),
            'create' => CreateThemeSetting::route('/create'),
            'edit' => EditThemeSetting::route('/{record}/edit'),
        ];
    }
}
