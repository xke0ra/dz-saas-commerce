<?php

use App\Actions\Domains\VerifyDomainOwnership;
use App\Enums\DomainStatus;
use App\Jobs\Domains\VerifyDomainOwnershipJob;
use App\Models\Domain;
use App\Support\Domains\DomainDnsLookup;

it('marks a domain active when the verification TXT record exists', function (): void {
    $domain = Domain::factory()->create([
        'hostname' => 'shop.example.dz',
        'status' => DomainStatus::PendingVerification,
        'verification_token' => 'test-token',
        'verified_at' => null,
        'last_checked_at' => null,
    ]);
    fakeDomainDns([
        $domain->verificationRecordName() => [$domain->verificationRecordValue()],
    ]);

    $verifiedDomain = app(VerifyDomainOwnership::class)->handle($domain);

    expect($verifiedDomain->status)->toBe(DomainStatus::Active)
        ->and($verifiedDomain->verified_at)->not->toBeNull()
        ->and($verifiedDomain->last_checked_at)->not->toBeNull()
        ->and($verifiedDomain->isResolvable())->toBeTrue();
});

it('accepts the raw verification token for compatibility with simple DNS providers', function (): void {
    $domain = Domain::factory()->create([
        'hostname' => 'raw-token.example.dz',
        'status' => DomainStatus::Failed,
        'verification_token' => 'raw-token-value',
        'verified_at' => null,
    ]);
    fakeDomainDns([
        $domain->verificationRecordName() => ['raw-token-value'],
    ]);

    $verifiedDomain = app(VerifyDomainOwnership::class)->handle($domain);

    expect($verifiedDomain->status)->toBe(DomainStatus::Active)
        ->and($verifiedDomain->isResolvable())->toBeTrue();
});

it('marks a domain failed when the verification TXT record is missing', function (): void {
    $domain = Domain::factory()->active()->create([
        'hostname' => 'missing.example.dz',
        'verification_token' => 'missing-token',
    ]);
    fakeDomainDns([
        $domain->verificationRecordName() => ['some-other-value'],
    ]);

    $verifiedDomain = app(VerifyDomainOwnership::class)->handle($domain);

    expect($verifiedDomain->status)->toBe(DomainStatus::Failed)
        ->and($verifiedDomain->verified_at)->toBeNull()
        ->and($verifiedDomain->last_checked_at)->not->toBeNull()
        ->and($verifiedDomain->isResolvable())->toBeFalse();
});

it('does not activate disabled domains during verification', function (): void {
    $domain = Domain::factory()->create([
        'hostname' => 'disabled-verify.example.dz',
        'status' => DomainStatus::Disabled,
        'verification_token' => 'disabled-token',
        'verified_at' => null,
    ]);
    fakeDomainDns([
        $domain->verificationRecordName() => [$domain->verificationRecordValue()],
    ]);

    $verifiedDomain = app(VerifyDomainOwnership::class)->handle($domain);

    expect($verifiedDomain->status)->toBe(DomainStatus::Disabled)
        ->and($verifiedDomain->verified_at)->toBeNull()
        ->and($verifiedDomain->last_checked_at)->not->toBeNull();
});

it('can verify a domain through the queued job handler', function (): void {
    $domain = Domain::factory()->create([
        'hostname' => 'queued.example.dz',
        'status' => DomainStatus::PendingVerification,
        'verification_token' => 'queued-token',
    ]);
    fakeDomainDns([
        $domain->verificationRecordName() => [$domain->verificationRecordValue()],
    ]);

    (new VerifyDomainOwnershipJob($domain->id))->handle(app(VerifyDomainOwnership::class));

    expect($domain->fresh()->status)->toBe(DomainStatus::Active);
});

/**
 * @param  array<string, array<int, string>>  $recordsByName
 */
function fakeDomainDns(array $recordsByName): void
{
    app()->instance(DomainDnsLookup::class, new class($recordsByName) extends DomainDnsLookup
    {
        /**
         * @param  array<string, array<int, string>>  $recordsByName
         */
        public function __construct(private readonly array $recordsByName) {}

        public function txtRecords(string $hostname): array
        {
            return $this->recordsByName[strtolower($hostname)] ?? [];
        }
    });
}
