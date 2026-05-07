<?php

namespace App\Filament\Vendor\Widgets;

use App\Filament\Vendor\Concerns\CanViewAnalyticsWidgets;
use App\Support\Analytics\TenantOrderAnalytics;
use App\Support\Tenancy\CurrentTenant;
use Filament\Widgets\ChartWidget;

class DeliveredSalesChartWidget extends ChartWidget
{
    use CanViewAnalyticsWidgets;

    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Delivered sales trend';

    protected string $color = 'success';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        return $this->chartData();
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    public function chartData(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return [
                'datasets' => [
                    [
                        'label' => 'Delivered sales',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $series = app(TenantOrderAnalytics::class)->dailyDeliveredSales($tenantId);

        return [
            'datasets' => [
                [
                    'label' => 'Delivered sales',
                    'data' => array_map(
                        fn (array $point): float => round($point['delivered_revenue_minor'] / 100, 2),
                        $series,
                    ),
                ],
            ],
            'labels' => array_map(fn (array $point): string => $point['label'], $series),
        ];
    }
}
