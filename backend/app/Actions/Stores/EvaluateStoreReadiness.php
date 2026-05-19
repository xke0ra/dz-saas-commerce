<?php

namespace App\Actions\Stores;

use App\Models\Store;
use App\Support\Readiness\StoreReadinessChecker;

class EvaluateStoreReadiness
{
    public function __construct(
        private readonly StoreReadinessChecker $checker,
    ) {}

    /**
     * @return array{ready: bool, errors: array<int, array{code: string, message: string}>, warnings: array<int, array{code: string, message: string}>}
     */
    public function handle(Store $store): array
    {
        return $this->checker->check($store)->toArray();
    }
}
