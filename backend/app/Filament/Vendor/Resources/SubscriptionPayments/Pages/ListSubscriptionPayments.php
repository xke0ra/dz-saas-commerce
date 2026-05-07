<?php

namespace App\Filament\Vendor\Resources\SubscriptionPayments\Pages;

use App\Filament\Vendor\Resources\SubscriptionPayments\SubscriptionPaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionPayments extends ListRecords
{
    protected static string $resource = SubscriptionPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record payment'),
        ];
    }
}
