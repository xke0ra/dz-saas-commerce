<?php

namespace App\Filament\Vendor\Resources\StaffMembers\Schemas;

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class StaffMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('role')
                    ->options(TenantRole::class)
                    ->default(TenantRole::StoreStaff->value)
                    ->required(),
                CheckboxList::make('permissions')
                    ->options(self::permissionOptions())
                    ->columns(2)
                    ->bulkToggleable()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function permissionOptions(): array
    {
        $options = [];

        foreach (TenantPermission::cases() as $permission) {
            $options[$permission->value] = $permission->getLabel() ?? $permission->value;
        }

        return $options;
    }
}
