<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DomainStatus: string implements HasColor, HasLabel
{
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Failed = 'failed';
    case Disabled = 'disabled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PendingVerification => 'Pending verification',
            self::Active => 'Active',
            self::Failed => 'Failed',
            self::Disabled => 'Disabled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PendingVerification => 'warning',
            self::Active => 'success',
            self::Failed => 'danger',
            self::Disabled => 'gray',
        };
    }
}
