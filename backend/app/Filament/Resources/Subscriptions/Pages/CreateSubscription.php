<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Actions\Billing\StartTenantSubscription;
use App\Filament\Resources\Subscriptions\Schemas\StartSubscriptionForm;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Plan;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return StartSubscriptionForm::configure($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Tenant::query()->findOrFail($data['tenant_id']);
        $plan = Plan::query()->findOrFail($data['plan_id']);

        return app(StartTenantSubscription::class)->handle(
            tenant: $tenant,
            plan: $plan,
            startsAt: Carbon::parse($data['starts_at']),
            trialEndsAt: filled($data['trial_ends_at'] ?? null) ? Carbon::parse($data['trial_ends_at']) : null,
            actor: auth()->user(),
            createInvoice: (bool) ($data['create_invoice'] ?? true),
            dueDays: (int) ($data['due_days'] ?? 7),
        );
    }
}
