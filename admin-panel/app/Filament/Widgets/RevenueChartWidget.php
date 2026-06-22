<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue (Last 30 Days)';

    protected function getData(): array
    {
        $data = Order::whereNotIn('status', ['cancelled', 'refunded'])
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw("DATE(created_at) as date"), DB::raw("SUM(total_amount) as revenue"))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('revenue', 'date')
            ->toArray();

        $dates = collect();
        $revenues = collect();

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dates->push(now()->subDays($i)->format('M d'));
            $revenues->push($data[$date] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (stars)',
                    'data' => $revenues->toArray(),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#f59e0b',
                ],
            ],
            'labels' => $dates->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
