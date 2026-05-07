<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlatformRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case PlatformSupport = 'platform_support';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::PlatformSupport => 'Platform support',
        };
    }
}
