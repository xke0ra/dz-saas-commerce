<?php

namespace App\Filament\Vendor\Resources\FailedDeliveryReasons\Pages;

use App\Filament\Vendor\Resources\FailedDeliveryReasons\FailedDeliveryReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFailedDeliveryReasons extends ListRecords
{
    protected static string $resource = FailedDeliveryReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
