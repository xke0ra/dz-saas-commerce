<?php

namespace App\Filament\Resources\SubscriptionPayments\Schemas;

use App\Enums\SubscriptionPaymentMethod;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubscriptionPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('invoice_id')
                    ->relationship('invoice', 'invoice_number')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('amount_minor')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Select::make('method')
                    ->options(SubscriptionPaymentMethod::class)
                    ->required()
                    ->default(SubscriptionPaymentMethod::ManualBankTransfer->value),
                TextInput::make('reference')
                    ->maxLength(255),
                FileUpload::make('proof_path')
                    ->label('Payment proof')
                    ->disk((string) config('billing.payment_proofs_disk', 'local'))
                    ->directory((string) config('billing.payment_proofs_directory', 'subscription-payment-proofs'))
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize((int) config('billing.payment_proofs_max_size_kb', 5120))
                    ->downloadable()
                    ->openable(),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
