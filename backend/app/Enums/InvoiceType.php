<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvoiceType: string implements HasLabel
{
    case SubscriptionInitial = 'subscription_initial';
    case SubscriptionRenewal = 'subscription_renewal';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SubscriptionInitial => 'Subscription initial',
            self::SubscriptionRenewal => 'Subscription renewal',
        };
    }
}
