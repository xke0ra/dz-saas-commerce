<?php

namespace App\Filament\Concerns;

use App\Models\Store;

trait AssignsDomainTenantFromStore
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->assignDomainTenantFromStore($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->assignDomainTenantFromStore($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function assignDomainTenantFromStore(array $data): array
    {
        $storeId = $data['store_id'] ?? null;

        if (! is_string($storeId)) {
            return $data;
        }

        $store = Store::query()
            ->withoutGlobalScope('current_tenant')
            ->find($storeId);

        if ($store !== null) {
            $data['tenant_id'] = $store->tenant_id;
        }

        return $data;
    }
}
