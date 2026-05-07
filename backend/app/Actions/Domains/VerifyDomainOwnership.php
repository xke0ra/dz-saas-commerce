<?php

namespace App\Actions\Domains;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Support\Domains\DomainDnsLookup;
use Illuminate\Support\Facades\DB;

class VerifyDomainOwnership
{
    public function __construct(private readonly DomainDnsLookup $dnsLookup) {}

    public function handle(Domain $domain): Domain
    {
        return DB::transaction(function () use ($domain): Domain {
            $domain = Domain::query()
                ->withoutGlobalScope('current_tenant')
                ->lockForUpdate()
                ->findOrFail($domain->id);

            if ($domain->status === DomainStatus::Disabled) {
                $domain->forceFill([
                    'last_checked_at' => now(),
                ])->save();

                return $domain->refresh();
            }

            $txtRecords = $this->dnsLookup->txtRecords($domain->verificationRecordName());
            $verified = in_array($domain->verificationRecordValue(), $txtRecords, true)
                || in_array($domain->verification_token, $txtRecords, true);

            $domain->forceFill([
                'status' => $verified ? DomainStatus::Active : DomainStatus::Failed,
                'verified_at' => $verified ? now() : null,
                'last_checked_at' => now(),
            ])->save();

            return $domain->refresh();
        });
    }
}
