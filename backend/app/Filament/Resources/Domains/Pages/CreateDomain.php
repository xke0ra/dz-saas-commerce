<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Concerns\AssignsDomainTenantFromStore;
use App\Filament\Resources\Domains\DomainResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDomain extends CreateRecord
{
    use AssignsDomainTenantFromStore;

    protected static string $resource = DomainResource::class;
}
