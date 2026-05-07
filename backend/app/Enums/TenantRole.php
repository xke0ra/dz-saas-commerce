<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TenantRole: string implements HasLabel
{
    case Owner = 'tenant_owner';
    case StoreAdmin = 'store_admin';
    case StoreStaff = 'store_staff';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Owner => 'Tenant owner',
            self::StoreAdmin => 'Store admin',
            self::StoreStaff => 'Store staff',
        };
    }
}
