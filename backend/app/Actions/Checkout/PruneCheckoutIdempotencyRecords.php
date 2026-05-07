<?php

namespace App\Actions\Checkout;

use App\Models\CheckoutIdempotencyRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PruneCheckoutIdempotencyRecords
{
    public function handle(?Carbon $expiredBefore = null): int
    {
        return (int) $this->expiredQuery($expiredBefore)->delete();
    }

    public function count(?Carbon $expiredBefore = null): int
    {
        return $this->expiredQuery($expiredBefore)->count();
    }

    private function expiredQuery(?Carbon $expiredBefore = null): Builder
    {
        // Cross-tenant maintenance command; it deletes only expired idempotency records.
        return CheckoutIdempotencyRecord::query()
            ->withoutGlobalScope('current_tenant')
            ->where('expires_at', '<=', $expiredBefore ?? now());
    }
}
