<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasLabel
{
    case Trialing = 'trialing';
    case Active = 'active';
    case GracePeriod = 'grace_period';
    case PastDue = 'past_due';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Suspended = 'suspended';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Trialing => 'Trialing',
            self::Active => 'Active',
            self::GracePeriod => 'Grace period',
            self::PastDue => 'Past due',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Suspended => 'Suspended',
        };
    }

    public function allowsFeatureAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::GracePeriod], true);
    }
}
