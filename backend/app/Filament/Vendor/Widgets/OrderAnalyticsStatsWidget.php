<?php

namespace App\Filament\Vendor\Widgets;

use App\Filament\Vendor\Concerns\CanViewAnalyticsWidgets;
use App\Support\Analytics\TenantOrderAnalytics;
use App\Support\Tenancy\CurrentTenant;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderAnalyticsStatsWidget extends StatsOverviewWidget
{
    use CanViewAnalyticsWidgets;

    protected static ?int $sort = -3;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Store analytics';

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $data = $this->statsData();
        $currency = $data['currency'];

        return [
            Stat::make('Orders last 30 days', $data['orders_count'])
                ->description($data['pending_orders_count'].' pending')
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('primary'),
            Stat::make('Delivered sales', $this->money($data['delivered_revenue_minor'], $currency))
                ->description($data['delivered_orders_count'].' delivered orders')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Average delivered order', $this->money($data['average_delivered_order_value_minor'], $currency))
                ->description('Per delivered order')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('info'),
            Stat::make('Failed delivery rate', number_format($data['failed_delivery_rate'], 2).'%')
                ->description($data['failed_delivery_orders_count'].' failed deliveries')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($data['failed_delivery_orders_count'] > 0 ? 'danger' : 'success'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statsData(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return [
                'currency' => 'DZD',
                'orders_count' => 0,
                'pending_orders_count' => 0,
                'confirmed_orders_count' => 0,
                'delivered_orders_count' => 0,
                'failed_delivery_orders_count' => 0,
                'cancelled_orders_count' => 0,
                'delivered_revenue_minor' => 0,
                'average_delivered_order_value_minor' => 0,
                'failed_delivery_rate' => 0.0,
            ];
        }

        return app(TenantOrderAnalytics::class)->summary($tenantId);
    }

    private function money(int $amountMinor, string $currency): string
    {
        return number_format($amountMinor / 100, 2).' '.$currency;
    }
}
