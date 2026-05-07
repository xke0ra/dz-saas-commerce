<?php

namespace App\Observers;

use App\Models\OrderReturn;
use Illuminate\Support\Str;

class OrderReturnObserver
{
    public function creating(OrderReturn $orderReturn): void
    {
        if ($orderReturn->return_number !== null) {
            return;
        }

        do {
            $returnNumber = 'RET-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (OrderReturn::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $orderReturn->tenant_id)
            ->where('return_number', $returnNumber)
            ->exists());

        $orderReturn->return_number = $returnNumber;
    }
}
