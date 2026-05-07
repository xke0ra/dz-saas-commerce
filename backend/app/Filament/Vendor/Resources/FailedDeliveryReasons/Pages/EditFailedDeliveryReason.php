<?php

namespace App\Filament\Vendor\Resources\FailedDeliveryReasons\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\FailedDeliveryReasons\FailedDeliveryReasonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFailedDeliveryReason extends EditRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = FailedDeliveryReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
