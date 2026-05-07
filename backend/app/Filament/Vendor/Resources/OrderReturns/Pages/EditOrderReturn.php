<?php

namespace App\Filament\Vendor\Resources\OrderReturns\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\OrderReturns\OrderReturnResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrderReturn extends EditRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = OrderReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
