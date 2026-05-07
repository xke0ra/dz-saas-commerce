<?php

namespace App\Filament\Vendor\Resources\Domains\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\Domains\DomainResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDomain extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = DomainResource::class;
}
