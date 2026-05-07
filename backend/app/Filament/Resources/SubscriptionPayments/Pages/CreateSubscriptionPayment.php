<?php

namespace App\Filament\Resources\SubscriptionPayments\Pages;

use App\Actions\Billing\RecordSubscriptionPayment;
use App\Enums\SubscriptionPaymentMethod;
use App\Filament\Resources\SubscriptionPayments\SubscriptionPaymentResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSubscriptionPayment extends CreateRecord
{
    protected static string $resource = SubscriptionPaymentResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $invoice = Invoice::query()
            ->withoutGlobalScope('current_tenant')
            ->findOrFail($data['invoice_id']);

        return app(RecordSubscriptionPayment::class)->handle(
            invoice: $invoice,
            amountMinor: (int) $data['amount_minor'],
            method: SubscriptionPaymentMethod::from($data['method']),
            reference: $data['reference'] ?? null,
            proofPath: $data['proof_path'] ?? null,
            metadata: $data['metadata'] ?? [],
            actor: auth()->user(),
        );
    }
}
