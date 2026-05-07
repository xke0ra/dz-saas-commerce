<?php

namespace App\Filament\Vendor\Resources\SubscriptionPayments\Schemas;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionPaymentMethod;
use App\Models\Invoice;
use App\Support\Tenancy\CurrentTenant;
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
                    ->label('Invoice')
                    ->options(fn (): array => self::openInvoiceOptions())
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('amount_minor')
                    ->label('Amount')
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

    /**
     * @return array<string, string>
     */
    private static function openInvoiceOptions(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return [];
        }

        return Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Overdue,
            ])
            ->where('balance_minor', '>', 0)
            ->orderByDesc('issued_at')
            ->get()
            ->mapWithKeys(fn (Invoice $invoice): array => [
                $invoice->id => $invoice->invoice_number.' - '.number_format($invoice->balance_minor / 100, 2).' '.$invoice->currency,
            ])
            ->all();
    }
}
