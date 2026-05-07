<?php

namespace App\Filament\Vendor\Resources\StaffInvitations;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\StaffInvitations\Pages\CreateStaffInvitation;
use App\Filament\Vendor\Resources\StaffInvitations\Pages\EditStaffInvitation;
use App\Filament\Vendor\Resources\StaffInvitations\Pages\ListStaffInvitations;
use App\Filament\Vendor\Resources\StaffInvitations\Schemas\StaffInvitationForm;
use App\Filament\Vendor\Resources\StaffInvitations\Tables\StaffInvitationsTable;
use App\Models\TenantInvitation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StaffInvitationResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = TenantInvitation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $navigationLabel = 'Staff invitations';

    protected static ?string $modelLabel = 'staff invitation';

    protected static ?string $pluralModelLabel = 'staff invitations';

    public static function form(Schema $schema): Schema
    {
        return StaffInvitationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffInvitationsTable::configure($table);
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
            'index' => ListStaffInvitations::route('/'),
            'create' => CreateStaffInvitation::route('/create'),
            'edit' => EditStaffInvitation::route('/{record}/edit'),
        ];
    }
}
