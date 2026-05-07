<?php

namespace App\Support\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SystemHealthChecker
{
    /**
     * @return array<string, mixed>
     */
    public function live(): array
    {
        return $this->report('live', [
            $this->check('app', fn (): array => [
                'status' => 'ok',
                'message' => 'Application booted.',
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function ready(): array
    {
        return $this->report('ready', [
            $this->check('database', fn (): array => $this->databaseCheck()),
            $this->check('cache', fn (): array => $this->cacheCheck()),
            $this->check('queue', fn (): array => $this->queueCheck()),
            $this->check('storage', fn (): array => $this->storageCheck()),
            $this->check('redis', fn (): array => $this->redisCheck()),
            $this->check('search', fn (): array => $this->searchCheck()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseCheck(): array
    {
        DB::select('select 1');

        return [
            'status' => 'ok',
            'message' => 'Database connection is available.',
            'meta' => [
                'connection' => config('database.default'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cacheCheck(): array
    {
        $key = 'system-health:'.(string) Str::uuid();

        Cache::put($key, 'ok', now()->addMinute());
        $value = Cache::get($key);
        Cache::forget($key);

        if ($value !== 'ok') {
            return [
                'status' => 'failed',
                'message' => 'Cache read-after-write check failed.',
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Cache store is writable.',
            'meta' => [
                'store' => config('cache.default'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueCheck(): array
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync') {
            return [
                'status' => 'ok',
                'message' => 'Queue uses sync driver.',
                'meta' => [
                    'connection' => $connection,
                ],
            ];
        }

        if ($connection === 'database') {
            $jobsTable = (string) config('queue.connections.database.table', 'jobs');
            $failedJobsTable = (string) config('queue.failed.table', 'failed_jobs');

            if (! Schema::hasTable($jobsTable)) {
                return [
                    'status' => 'failed',
                    'message' => "Queue table [{$jobsTable}] is missing.",
                    'meta' => [
                        'connection' => $connection,
                    ],
                ];
            }

            return [
                'status' => 'ok',
                'message' => 'Database queue tables are available.',
                'meta' => [
                    'connection' => $connection,
                    'failed_jobs' => Schema::hasTable($failedJobsTable) ? DB::table($failedJobsTable)->count() : null,
                ],
            ];
        }

        if ($connection === 'redis') {
            Redis::connection((string) config('queue.connections.redis.connection', 'default'))->ping();

            return [
                'status' => 'ok',
                'message' => 'Redis queue connection is available.',
                'meta' => [
                    'connection' => $connection,
                ],
            ];
        }

        return [
            'status' => 'warning',
            'message' => "Queue connection [{$connection}] has no readiness probe yet.",
            'meta' => [
                'connection' => $connection,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storageCheck(): array
    {
        $disk = (string) config('filesystems.default');
        $path = 'health/readiness-'.(string) Str::uuid().'.txt';

        Storage::disk($disk)->put($path, 'ok');
        $value = Storage::disk($disk)->get($path);
        Storage::disk($disk)->delete($path);

        if ($value !== 'ok') {
            return [
                'status' => 'failed',
                'message' => 'Storage read-after-write check failed.',
                'meta' => [
                    'disk' => $disk,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Storage disk is writable.',
            'meta' => [
                'disk' => $disk,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function redisCheck(): array
    {
        if (! $this->redisRequired()) {
            return [
                'status' => 'skipped',
                'message' => 'Redis is not required by current cache/session/queue configuration.',
            ];
        }

        Redis::connection()->ping();

        return [
            'status' => 'ok',
            'message' => 'Redis connection is available.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function searchCheck(): array
    {
        $driver = (string) config('scout.driver');

        if ($driver !== 'meilisearch') {
            return [
                'status' => 'skipped',
                'message' => "Scout driver [{$driver}] does not require Meilisearch readiness.",
                'meta' => [
                    'driver' => $driver,
                ],
            ];
        }

        $host = rtrim((string) config('scout.meilisearch.host'), '/');
        $key = config('scout.meilisearch.key');
        $context = [
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true,
                'header' => filled($key) ? "Authorization: Bearer {$key}\r\n" : '',
            ],
        ];
        $response = @file_get_contents($host.'/health', false, stream_context_create($context));

        if ($response === false) {
            return [
                'status' => 'failed',
                'message' => 'Meilisearch health endpoint is unavailable.',
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Meilisearch health endpoint responded.',
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function check(string $name, callable $callback): array
    {
        $startedAt = microtime(true);

        try {
            $result = $callback();
        } catch (Throwable $throwable) {
            $result = [
                'status' => 'failed',
                'message' => $throwable->getMessage(),
            ];
        }

        return [
            'name' => $name,
            'status' => $result['status'] ?? 'failed',
            'message' => $result['message'] ?? null,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'meta' => $result['meta'] ?? [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @return array<string, mixed>
     */
    private function report(string $scope, array $checks): array
    {
        $failed = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'failed');

        return [
            'status' => $failed ? 'failed' : 'ok',
            'scope' => $scope,
            'checked_at' => now()->toISOString(),
            'checks' => $checks,
        ];
    }

    private function redisRequired(): bool
    {
        return config('cache.default') === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';
    }
}
