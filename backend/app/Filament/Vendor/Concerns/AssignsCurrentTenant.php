<?php

namespace App\Filament\Vendor\Concerns;

use App\Support\Tenancy\CurrentTenant;

trait AssignsCurrentTenant
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->assignCurrentTenant($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->assignCurrentTenant($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function assignCurrentTenant(array $data): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }

        return $data;
    }
}
