<?php

namespace App\Jobs\Domains;

use App\Actions\Domains\VerifyDomainOwnership;
use App\Models\Domain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyDomainOwnershipJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $domainId) {}

    public function handle(VerifyDomainOwnership $verifyDomainOwnership): void
    {
        $domain = Domain::query()
            ->withoutGlobalScope('current_tenant')
            ->find($this->domainId);

        if ($domain === null) {
            return;
        }

        $verifyDomainOwnership->handle($domain);
    }
}
