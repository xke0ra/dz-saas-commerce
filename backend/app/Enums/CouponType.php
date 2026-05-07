<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CouponType: string implements HasLabel
{
    case FixedAmount = 'fixed_amount';
    case Percentage = 'percentage';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FixedAmount => 'Fixed amount',
            self::Percentage => 'Percentage',
        };
    }
}
