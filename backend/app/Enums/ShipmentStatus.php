<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ShipmentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case FailedDelivery = 'failed_delivery';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::ReadyToShip => 'Ready to ship',
            self::Shipped => 'Shipped',
            self::InTransit => 'In transit',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::FailedDelivery => 'Failed delivery',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::ReadyToShip => 'warning',
            self::Shipped, self::InTransit, self::OutForDelivery => 'info',
            self::Delivered => 'success',
            self::FailedDelivery, self::Returned, self::Cancelled => 'danger',
        };
    }
}
