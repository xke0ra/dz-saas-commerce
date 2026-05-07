<?php

namespace App\Support\Analytics;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Tenant;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class TenantOrderAnalytics
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Tenant|string $tenant, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->tenantId($tenant);
        [$from, $to] = $this->period($from, $to, defaultDays: 30);

        $orders = Order::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'status', 'total_minor', 'currency', 'created_at']);

        $deliveredOrders = $orders->filter(fn (Order $order): bool => $order->status === OrderStatus::Delivered);
        $failedDeliveryOrders = $orders->filter(fn (Order $order): bool => $order->status === OrderStatus::FailedDelivery);
        $cancelledOrders = $orders->filter(fn (Order $order): bool => $order->status === OrderStatus::Cancelled);
        $ordersCount = $orders->count();
        $deliveredOrdersCount = $deliveredOrders->count();
        $deliveredRevenueMinor = (int) $deliveredOrders->sum('total_minor');

        return [
            'from' => $from,
            'to' => $to,
            'currency' => $orders->first()?->currency ?? 'DZD',
            'orders_count' => $ordersCount,
            'pending_orders_count' => $orders->filter(fn (Order $order): bool => $order->status === OrderStatus::Pending)->count(),
            'confirmed_orders_count' => $orders->filter(fn (Order $order): bool => $order->status === OrderStatus::Confirmed)->count(),
            'delivered_orders_count' => $deliveredOrdersCount,
            'failed_delivery_orders_count' => $failedDeliveryOrders->count(),
            'cancelled_orders_count' => $cancelledOrders->count(),
            'delivered_revenue_minor' => $deliveredRevenueMinor,
            'average_delivered_order_value_minor' => $deliveredOrdersCount > 0
                ? intdiv($deliveredRevenueMinor, $deliveredOrdersCount)
                : 0,
            'failed_delivery_rate' => $ordersCount > 0
                ? round(($failedDeliveryOrders->count() / $ordersCount) * 100, 2)
                : 0.0,
        ];
    }

    /**
     * @return array<int, array{date: string, label: string, delivered_orders_count: int, delivered_revenue_minor: int}>
     */
    public function dailyDeliveredSales(Tenant|string $tenant, int $days = 14, ?Carbon $to = null): array
    {
        $tenantId = $this->tenantId($tenant);
        $to = ($to ?? now())->copy()->endOfDay();
        $from = $to->copy()->subDays(max(1, $days) - 1)->startOfDay();

        $series = [];

        foreach (CarbonPeriod::create($from, '1 day', $to) as $date) {
            $key = $date->format('Y-m-d');

            $series[$key] = [
                'date' => $key,
                'label' => $date->format('M d'),
                'delivered_orders_count' => 0,
                'delivered_revenue_minor' => 0,
            ];
        }

        Order::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->where('status', OrderStatus::Delivered->value)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'total_minor', 'created_at'])
            ->each(function (Order $order) use (&$series): void {
                $key = $order->created_at?->format('Y-m-d');

                if ($key === null || ! array_key_exists($key, $series)) {
                    return;
                }

                $series[$key]['delivered_orders_count']++;
                $series[$key]['delivered_revenue_minor'] += $order->total_minor;
            });

        return array_values($series);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(?Carbon $from, ?Carbon $to, int $defaultDays): array
    {
        $to = ($to ?? now())->copy()->endOfDay();
        $from = ($from ?? $to->copy()->subDays($defaultDays - 1))->copy()->startOfDay();

        return [$from, $to];
    }

    private function tenantId(Tenant|string $tenant): string
    {
        return $tenant instanceof Tenant ? $tenant->id : $tenant;
    }
}
