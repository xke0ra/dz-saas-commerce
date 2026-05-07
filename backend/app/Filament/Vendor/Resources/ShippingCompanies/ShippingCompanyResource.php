<?php

namespace App\Filament\Vendor\Resources\ShippingCompanies;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\ShippingCompanies\Pages\CreateShippingCompany;
use App\Filament\Vendor\Resources\ShippingCompanies\Pages\EditShippingCompany;
use App\Filament\Vendor\Resources\ShippingCompanies\Pages\ListShippingCompanies;
use App\Filament\Vendor\Resources\ShippingCompanies\Schemas\ShippingCompanyForm;
use App\Filament\Vendor\Resources\ShippingCompanies\Tables\ShippingCompaniesTable;
use App\Models\ShippingCompany;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShippingCompanyResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = ShippingCompany::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ShippingCompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingCompaniesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingCompanies::route('/'),
            'create' => CreateShippingCompany::route('/create'),
            'edit' => EditShippingCompany::route('/{record}/edit'),
        ];
    }
}
