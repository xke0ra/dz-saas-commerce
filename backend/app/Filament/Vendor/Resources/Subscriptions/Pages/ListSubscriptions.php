<?php

namespace App\Filament\Vendor\Resources\Subscriptions\Pages;

use App\Filament\Vendor\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;
}
