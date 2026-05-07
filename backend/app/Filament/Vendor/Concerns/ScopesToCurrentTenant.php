<?php

namespace App\Filament\Vendor\Concerns;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;

trait ScopesToCurrentTenant
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->getModel()->qualifyColumn('tenant_id'), $tenantId);
    }
}
