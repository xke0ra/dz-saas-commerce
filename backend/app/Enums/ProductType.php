<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasColor, HasLabel
{
    case Simple = 'simple';
    case Variable = 'variable';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Simple => 'Simple',
            self::Variable => 'Variable',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Simple => 'gray',
            self::Variable => 'info',
        };
    }
}
