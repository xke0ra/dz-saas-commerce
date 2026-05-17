<?php

use App\Actions\Billing\ProcessBillingLifecycle;
use App\Actions\Checkout\PruneCheckoutIdempotencyRecords;
use App\Jobs\Billing\ProcessBillingLifecycleJob;
use App\Models\User;
use App\Support\Auth\TwoFactorAuthentication;
use App\Support\System\SystemHealthChecker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:process {--sync : Run lifecycle processing immediately instead of queueing a job}', function (): int {
    if (! $this->option('sync')) {
        ProcessBillingLifecycleJob::dispatch();
        $this->info('Billing lifecycle processing job dispatched.');

        return Command::SUCCESS;
    }

    $counts = app(ProcessBillingLifecycle::class)->handle();

    foreach ($counts as $key => $count) {
        $this->line($key.': '.$count);
    }

    return Command::SUCCESS;
})->purpose('Process SaaS billing renewal invoices, reminders, grace periods, overdue invoices, and subscription suspensions');

Artisan::command('checkout-idempotency:prune {--dry-run : Count expired records without deleting them}', function (): int {
    $pruner = app(PruneCheckoutIdempotencyRecords::class);

    if ($this->option('dry-run')) {
        $count = $pruner->count();
        $this->info("Found {$count} expired checkout idempotency record(s).");

        return Command::SUCCESS;
    }

    $deleted = $pruner->handle();
    $this->info("Pruned {$deleted} expired checkout idempotency record(s).");

    return Command::SUCCESS;
})->purpose('Prune expired checkout idempotency records');

Artisan::command('security:reset-two-factor
    {--user-id= : Target user ID}
    {--email= : Target user email}
    {--reason= : Required operator reason}
    {--actor-id= : Operator user ID}
    {--actor-email= : Operator user email}
    {--dry-run : Show what would happen without changing data}
    {--confirm : Confirm the emergency 2FA reset}', function (): int {
    $reason = trim((string) $this->option('reason'));
    $userId = trim((string) $this->option('user-id'));
    $email = trim((string) $this->option('email'));
    $actorId = trim((string) $this->option('actor-id'));
    $actorEmail = trim((string) $this->option('actor-email'));

    if (! $this->option('confirm')) {
        $this->error('Refusing to reset two-factor authentication without --confirm.');

        return Command::INVALID;
    }

    if ($reason === '') {
        $this->error('A non-empty --reason is required.');

        return Command::INVALID;
    }

    if (($userId === '') === ($email === '')) {
        $this->error('Provide exactly one target identifier: --user-id or --email.');

        return Command::INVALID;
    }

    if ($actorId !== '' && $actorEmail !== '') {
        $this->error('Provide at most one actor identifier: --actor-id or --actor-email.');

        return Command::INVALID;
    }

    $target = $userId !== ''
        ? User::query()->whereKey($userId)->first()
        : User::query()->where('email', $email)->first();

    if (! $target instanceof User) {
        $this->error('Target user was not found.');

        return Command::FAILURE;
    }

    $actor = null;

    if ($actorId !== '' || $actorEmail !== '') {
        $actor = $actorId !== ''
            ? User::query()->whereKey($actorId)->first()
            : User::query()->where('email', $actorEmail)->first();

        if (! $actor instanceof User) {
            $this->error('Actor user was not found.');

            return Command::FAILURE;
        }
    }

    if (! $target->hasTwoFactorAuthenticationEnabled()) {
        $this->info('No reset performed: target user does not have two-factor authentication enabled.');

        return Command::SUCCESS;
    }

    $this->warn('This will clear the target user two-factor secret and recovery codes.');
    $this->warn('If their panel role requires 2FA, they will be redirected to setup on next panel access.');

    if ($this->option('dry-run')) {
        $this->info("Dry run only: 2FA would be reset for user ID {$target->id}.");

        return Command::SUCCESS;
    }

    app(TwoFactorAuthentication::class)->resetForUser(
        target: $target,
        actor: $actor,
        reason: $reason,
        source: 'artisan',
    );

    $this->info("2FA reset completed for user ID {$target->id}.");

    return Command::SUCCESS;
})->purpose('Emergency reset app-based two-factor authentication for a specific user');

Artisan::command('system:health {--scope=ready : Health scope: live or ready} {--format=table : Output format: table or json}', function (): int {
    $scope = strtolower((string) $this->option('scope'));
    $format = strtolower((string) $this->option('format'));

    if (! in_array($scope, ['live', 'ready'], true)) {
        $this->error('Invalid --scope. Supported values: live, ready.');

        return Command::INVALID;
    }

    if (! in_array($format, ['table', 'json'], true)) {
        $this->error('Invalid --format. Supported values: table, json.');

        return Command::INVALID;
    }

    $report = $scope === 'live'
        ? app(SystemHealthChecker::class)->live()
        : app(SystemHealthChecker::class)->ready();

    if ($format === 'json') {
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    } else {
        $this->info("System health [{$report['scope']}]: {$report['status']}");
        $this->table(
            ['Check', 'Status', 'Duration ms', 'Message'],
            collect($report['checks'])->map(fn (array $check): array => [
                $check['name'],
                $check['status'],
                $check['duration_ms'],
                $check['message'],
            ])->all(),
        );
    }

    return $report['status'] === 'ok' ? Command::SUCCESS : Command::FAILURE;
})->purpose('Run liveness or readiness checks for production and CI smoke verification');
