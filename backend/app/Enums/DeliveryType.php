<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DeliveryType: string implements HasLabel
{
    case Home = 'home';
    case Desk = 'desk';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Home => 'Home delivery',
            self::Desk => 'Desk delivery',
        };
    }
}
