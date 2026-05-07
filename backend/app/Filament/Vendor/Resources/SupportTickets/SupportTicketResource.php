<?php

namespace App\Filament\Vendor\Resources\SupportTickets;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\SupportTickets\Pages\CreateSupportTicket;
use App\Filament\Vendor\Resources\SupportTickets\Pages\EditSupportTicket;
use App\Filament\Vendor\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Vendor\Resources\SupportTickets\Schemas\SupportTicketForm;
use App\Filament\Vendor\Resources\SupportTickets\Tables\SupportTicketsTable;
use App\Models\SupportTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 95;

    protected static ?string $recordTitleAttribute = 'ticket_number';

    protected static ?string $modelLabel = 'support ticket';

    protected static ?string $pluralModelLabel = 'support tickets';

    public static function form(Schema $schema): Schema
    {
        return SupportTicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'create' => CreateSupportTicket::route('/create'),
            'edit' => EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
