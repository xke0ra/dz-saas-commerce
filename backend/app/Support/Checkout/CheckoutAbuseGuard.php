<?php

namespace App\Support\Checkout;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class CheckoutAbuseGuard
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function guard(Request $request, Store $store, array $payload): void
    {
        $phone = (string) ($payload['phone'] ?? 'unknown');
        $ip = $request->ip() ?: 'unknown';

        $limits = [
            [
                'scope' => 'ip',
                'key' => 'checkout:ip:'.sha1($ip),
                'max_attempts' => 120,
                'decay_seconds' => 60,
            ],
            [
                'scope' => 'phone',
                'key' => "checkout:phone:{$store->tenant_id}:{$store->id}:".sha1($phone),
                'max_attempts' => 10,
                'decay_seconds' => 600,
            ],
            [
                'scope' => 'store',
                'key' => "checkout:store:{$store->tenant_id}:{$store->id}",
                'max_attempts' => 300,
                'decay_seconds' => 60,
            ],
        ];

        foreach ($limits as $limit) {
            if (! RateLimiter::tooManyAttempts($limit['key'], $limit['max_attempts'])) {
                continue;
            }

            $retryAfter = RateLimiter::availableIn($limit['key']);

            Log::warning('checkout_rate_limit_exceeded', [
                'scope' => $limit['scope'],
                'tenant_id' => $store->tenant_id,
                'store_id' => $store->id,
                'ip_hash' => sha1($ip),
                'phone_hash' => sha1($phone),
                'retry_after' => $retryAfter,
            ]);

            throw new TooManyRequestsHttpException($retryAfter, 'Too many checkout attempts. Please try again later.');
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit['key'], $limit['decay_seconds']);
        }
    }
}
