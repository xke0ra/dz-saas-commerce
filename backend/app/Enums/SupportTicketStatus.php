<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SupportTicketStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Pending = 'pending';
    case WaitingForMerchant = 'waiting_for_merchant';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Pending',
            self::WaitingForMerchant => 'Waiting for merchant',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'info',
            self::Pending => 'warning',
            self::WaitingForMerchant => 'gray',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
    }
}
