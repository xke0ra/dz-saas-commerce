<?php

namespace App\Support\Billing;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class BillingPeriod
{
    public function end(CarbonInterface $startsAt, string $interval): Carbon
    {
        $startsAt = Carbon::parse($startsAt);

        return match ($interval) {
            'yearly', 'annual', 'annually' => $startsAt->copy()->addYearNoOverflow(),
            default => $startsAt->copy()->addMonthNoOverflow(),
        };
    }
}
