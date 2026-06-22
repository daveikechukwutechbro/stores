<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRevenue = Order::whereNotIn('status', ['cancelled', 'refunded'])->sum('total_amount');
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $totalProducts = Product::count();
        $totalUsers = User::count();
        $lowStockCount = Product::where('stock', '<=', 5)->where('stock', '>', 0)->count();
        $outOfStockCount = Product::where('stock', 0)->count();

        return [
            Stat::make('Total Revenue', number_format($totalRevenue) . ' ⭐')
                ->description('All time revenue')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Orders', $totalOrders)
                ->description($pendingOrders . ' pending')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('info'),

            Stat::make('Products', $totalProducts)
                ->description($lowStockCount . ' low stock, ' . $outOfStockCount . ' out of stock')
                ->descriptionIcon('heroicon-m-cube')
                ->color($lowStockCount > 0 ? 'warning' : 'success'),

            Stat::make('Users', $totalUsers)
                ->description('Registered customers')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
        ];
    }
}
