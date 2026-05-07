<?php

namespace App\Filament\Vendor\Resources\OrderReturns\Pages;

use App\Filament\Vendor\Resources\OrderReturns\OrderReturnResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrderReturns extends ListRecords
{
    protected static string $resource = OrderReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
