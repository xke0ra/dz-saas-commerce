<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasLabel
{
    case Reserved = 'reserved';
    case Released = 'released';
    case Settled = 'settled';
    case Restocked = 'restocked';
    case ManualAdjustment = 'manual_adjustment';
    case Correction = 'correction';
    case Import = 'import';
    case ReturnReceived = 'return_received';

    public function getLabel(): ?string
    {
        return str($this->value)
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }
}
