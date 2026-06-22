<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;

class LowStockProductsWidget extends BaseTableWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Low Stock Products';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::where('stock', '<=', 5)->orderBy('stock')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->sortable()
                    ->color(fn ($state): string => $state === 0 ? 'danger' : ($state <= 3 ? 'warning' : 'gray'))
                    ->badge(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => number_format($state) . ' ⭐'),
            ])
            ->actions([
                Tables\Actions\Action::make('restock')
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-m-arrow-trending-up'),
            ]);
    }
}
