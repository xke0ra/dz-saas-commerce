<?php

namespace App\Filament\Vendor\Resources\Domains;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\Domains\Pages\CreateDomain;
use App\Filament\Vendor\Resources\Domains\Pages\EditDomain;
use App\Filament\Vendor\Resources\Domains\Pages\ListDomains;
use App\Filament\Vendor\Resources\Domains\Schemas\DomainForm;
use App\Filament\Vendor\Resources\Domains\Tables\DomainsTable;
use App\Models\Domain;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DomainResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = Domain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Domains';

    protected static ?string $recordTitleAttribute = 'hostname';

    public static function form(Schema $schema): Schema
    {
        return DomainForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DomainsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDomains::route('/'),
            'create' => CreateDomain::route('/create'),
            'edit' => EditDomain::route('/{record}/edit'),
        ];
    }
}
