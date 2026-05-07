<?php

namespace App\Filament\Vendor\Resources\StaffInvitations\Schemas;

use App\Enums\TenantInvitationStatus;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StaffInvitationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255)
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
                Select::make('status')
                    ->options(TenantInvitationStatus::class)
                    ->disabled()
                    ->dehydrated(false),
                DateTimePicker::make('expires_at')
                    ->disabled()
                    ->dehydrated(false),
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
