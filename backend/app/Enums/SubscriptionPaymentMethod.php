<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionPaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case ManualBankTransfer = 'manual_bank_transfer';
    case ManualPaymentProof = 'manual_payment_proof';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::ManualBankTransfer => 'Manual bank transfer',
            self::ManualPaymentProof => 'Manual payment proof',
        };
    }
}
