<?php

namespace App\Support\Checkout;

use App\Data\Checkout\CheckoutOrderResult;
use App\Models\CheckoutIdempotencyRecord;
use App\Models\Order;
use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CheckoutIdempotency
{
    private const EXPIRATION_HOURS = 24;

    private const DUPLICATE_WINDOW_SECONDS = 60;

    public function __construct(
        private readonly CheckoutRequestHasher $hasher,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(): Order  $createOrder
     */
    public function handle(Request $request, Store $store, array $payload, Closure $createOrder): CheckoutOrderResult
    {
        $requestHash = $this->hasher->hash($payload);
        $customerPhone = (string) $payload['phone'];
        $idempotencyKey = $this->idempotencyKey($request);

        if ($idempotencyKey === null) {
            return $this->handleDuplicateWindow($request, $store, $payload, $requestHash, $customerPhone, $createOrder);
        }

        return DB::transaction(function () use ($request, $store, $payload, $requestHash, $customerPhone, $idempotencyKey, $createOrder): CheckoutOrderResult {
            $record = CheckoutIdempotencyRecord::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $store->tenant_id)
                ->where('store_id', $store->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($record !== null) {
                return $this->replayOrReject($record, $store, $requestHash, $request);
            }

            $record = CheckoutIdempotencyRecord::query()
                ->withoutGlobalScope('current_tenant')
                ->create($this->recordAttributes(
                    request: $request,
                    store: $store,
                    payload: $payload,
                    requestHash: $requestHash,
                    customerPhone: $customerPhone,
                    idempotencyKey: $idempotencyKey,
                ));

            $order = $createOrder();

            $record->update([
                'order_id' => $order->id,
                'response_status' => 201,
                'completed_at' => now(),
            ]);

            return new CheckoutOrderResult($this->loadOrder($order), 201);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(): Order  $createOrder
     */
    private function handleDuplicateWindow(Request $request, Store $store, array $payload, string $requestHash, string $customerPhone, Closure $createOrder): CheckoutOrderResult
    {
        return DB::transaction(function () use ($request, $store, $payload, $requestHash, $customerPhone, $createOrder): CheckoutOrderResult {
            $record = CheckoutIdempotencyRecord::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $store->tenant_id)
                ->where('store_id', $store->id)
                ->whereNull('idempotency_key')
                ->where('customer_phone', $customerPhone)
                ->where('request_hash', $requestHash)
                ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
                ->whereNotNull('order_id')
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($record !== null) {
                Log::info('checkout_duplicate_window_replay', [
                    'tenant_id' => $store->tenant_id,
                    'store_id' => $store->id,
                    'record_id' => $record->id,
                    'order_id' => $record->order_id,
                    'ip_hash' => sha1($request->ip() ?: 'unknown'),
                    'phone_hash' => sha1($customerPhone),
                ]);

                return new CheckoutOrderResult($this->loadOrderFromRecord($record, $store), 200);
            }

            $record = CheckoutIdempotencyRecord::query()
                ->withoutGlobalScope('current_tenant')
                ->create($this->recordAttributes(
                    request: $request,
                    store: $store,
                    payload: $payload,
                    requestHash: $requestHash,
                    customerPhone: $customerPhone,
                    idempotencyKey: null,
                ));

            $order = $createOrder();

            $record->update([
                'order_id' => $order->id,
                'response_status' => 201,
                'completed_at' => now(),
            ]);

            return new CheckoutOrderResult($this->loadOrder($order), 201);
        });
    }

    private function replayOrReject(CheckoutIdempotencyRecord $record, Store $store, string $requestHash, Request $request): CheckoutOrderResult
    {
        if (! hash_equals($record->request_hash, $requestHash)) {
            Log::warning('checkout_idempotency_conflict', [
                'tenant_id' => $store->tenant_id,
                'store_id' => $store->id,
                'record_id' => $record->id,
                'order_id' => $record->order_id,
                'ip_hash' => sha1($request->ip() ?: 'unknown'),
            ]);

            throw new ConflictHttpException('Idempotency-Key has already been used with a different checkout payload.');
        }

        if ($record->order_id === null) {
            Log::warning('checkout_idempotency_in_progress', [
                'tenant_id' => $store->tenant_id,
                'store_id' => $store->id,
                'record_id' => $record->id,
                'ip_hash' => sha1($request->ip() ?: 'unknown'),
            ]);

            throw new ConflictHttpException('A checkout request with this Idempotency-Key is still processing.');
        }

        return new CheckoutOrderResult($this->loadOrderFromRecord($record, $store), $record->response_status);
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '') {
            return null;
        }

        if (strlen($key) > 255 || ! preg_match('/^[A-Za-z0-9._:-]+$/', $key)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The Idempotency-Key header must be 255 characters or fewer and contain only letters, numbers, dots, underscores, colons, or hyphens.',
            ]);
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function recordAttributes(Request $request, Store $store, array $payload, string $requestHash, string $customerPhone, ?string $idempotencyKey): array
    {
        return [
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'customer_phone' => $customerPhone,
            'response_status' => 201,
            'expires_at' => now()->addHours(self::EXPIRATION_HOURS),
            'metadata' => [
                'ip_hash' => sha1($request->ip() ?: 'unknown'),
                'user_agent_hash' => sha1((string) $request->userAgent()),
                'item_count' => count($payload['items'] ?? [$payload['product_id'] ?? null]),
            ],
        ];
    }

    private function loadOrderFromRecord(CheckoutIdempotencyRecord $record, Store $store): Order
    {
        $order = Order::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id)
            ->whereKey($record->order_id)
            ->firstOrFail();

        return $this->loadOrder($order);
    }

    private function loadOrder(Order $order): Order
    {
        return $order->load(['customer', 'items', 'payments.paymentMethod', 'coupon', 'couponRedemptions', 'wilaya', 'commune']);
    }
}
