<?php

use App\Actions\Billing\ProcessBillingLifecycle;
use App\Jobs\Billing\ProcessBillingLifecycleJob;
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
