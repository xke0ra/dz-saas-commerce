<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethodType: string implements HasLabel
{
    case CashOnDelivery = 'cash_on_delivery';
    case ManualBankTransfer = 'manual_bank_transfer';
    case ManualPaymentProof = 'manual_payment_proof';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on delivery',
            self::ManualBankTransfer => 'Manual bank transfer',
            self::ManualPaymentProof => 'Manual payment proof',
        };
    }
}
