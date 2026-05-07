<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SupportTicketCategory: string implements HasLabel
{
    case General = 'general';
    case Billing = 'billing';
    case Technical = 'technical';
    case Storefront = 'storefront';
    case Orders = 'orders';
    case Shipping = 'shipping';
    case Domains = 'domains';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::General => 'General',
            self::Billing => 'Billing',
            self::Technical => 'Technical',
            self::Storefront => 'Storefront',
            self::Orders => 'Orders',
            self::Shipping => 'Shipping',
            self::Domains => 'Domains',
        };
    }
}
