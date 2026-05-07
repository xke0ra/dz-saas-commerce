<?php

namespace App\Jobs\Billing;

use App\Actions\Billing\ProcessBillingLifecycle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBillingLifecycleJob implements ShouldQueue
{
    use Queueable;

    public function handle(ProcessBillingLifecycle $processBillingLifecycle): void
    {
        $processBillingLifecycle->handle();
    }
}
