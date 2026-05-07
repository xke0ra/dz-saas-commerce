<?php

namespace App\Filament\Vendor\Resources\StaffMembers;

use App\Filament\Vendor\Concerns\ScopesToCurrentTenant;
use App\Filament\Vendor\Resources\StaffMembers\Pages\CreateStaffMember;
use App\Filament\Vendor\Resources\StaffMembers\Pages\EditStaffMember;
use App\Filament\Vendor\Resources\StaffMembers\Pages\ListStaffMembers;
use App\Filament\Vendor\Resources\StaffMembers\Schemas\StaffMemberForm;
use App\Filament\Vendor\Resources\StaffMembers\Tables\StaffMembersTable;
use App\Models\TenantUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StaffMemberResource extends Resource
{
    use ScopesToCurrentTenant;

    protected static ?string $model = TenantUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    public static function form(Schema $schema): Schema
    {
        return StaffMemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffMembersTable::configure($table);
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
            'index' => ListStaffMembers::route('/'),
            'create' => CreateStaffMember::route('/create'),
            'edit' => EditStaffMember::route('/{record}/edit'),
        ];
    }
}
