<?php

namespace App\Filament\Vendor\Resources\SubscriptionPayments\Pages;

use App\Actions\Billing\RecordSubscriptionPayment;
use App\Enums\SubscriptionPaymentMethod;
use App\Filament\Vendor\Resources\SubscriptionPayments\SubscriptionPaymentResource;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
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
        $tenantId = app(CurrentTenant::class)->id();

        abort_if($tenantId === null, 403);

        $invoice = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($data['invoice_id']);

        $user = auth()->user();

        return app(RecordSubscriptionPayment::class)->handle(
            invoice: $invoice,
            amountMinor: (int) $data['amount_minor'],
            method: SubscriptionPaymentMethod::from($data['method']),
            reference: $data['reference'] ?? null,
            proofPath: $data['proof_path'] ?? null,
            metadata: $data['metadata'] ?? [],
            actor: $user instanceof User ? $user : null,
        );
    }
}
