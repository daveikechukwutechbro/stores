<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $modelLabel = 'Order';

    protected static ?string $pluralLabel = 'Orders';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() > 0 ? 'warning' : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order Info')->schema([
                    Forms\Components\TextInput::make('order_number')
                        ->required()
                        ->disabled(),
                    Forms\Components\Select::make('user_id')
                        ->relationship('user', 'name')
                        ->required()
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'confirmed' => 'Confirmed',
                            'processing' => 'Processing',
                            'shipped' => 'Shipped',
                            'delivered' => 'Delivered',
                            'cancelled' => 'Cancelled',
                            'refunded' => 'Refunded',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('total_amount')
                        ->required()
                        ->numeric()
                        ->prefix('⭐'),
                    Forms\Components\TextInput::make('payment_method'),
                ])->columns(3),

                Forms\Components\Section::make('Shipping')->schema([
                    Forms\Components\TextInput::make('shipping_address'),
                    Forms\Components\TextInput::make('phone')->tel(),
                    Forms\Components\Textarea::make('note')->columnSpanFull(),
                ])->columns(2),

                Forms\Components\Section::make('Payment')->schema([
                    Forms\Components\TextInput::make('transaction_id'),
                    Forms\Components\TextInput::make('telegram_charge_id'),
                    Forms\Components\DateTimePicker::make('estimated_delivery'),
                    Forms\Components\DateTimePicker::make('delivered_at'),
                ])->columns(2),

                Forms\Components\Section::make('Order Items')->schema([
                    Forms\Components\Placeholder::make('items_preview')
                        ->content(function ($record) {
                            if (!$record || !$record->items->count()) {
                                return 'No items';
                            }
                            $html = '<table class="w-full text-sm"><thead><tr><th class="text-left">Item</th><th>Qty</th><th>Price</th><th class="text-right">Total</th></tr></thead><tbody>';
                            foreach ($record->items as $item) {
                                $html .= "<tr><td>{$item->name}</td><td class='text-center'>{$item->quantity}</td><td class='text-center'>{$item->price}⭐</td><td class='text-right'>" . ($item->price * $item->quantity) . "⭐</td></tr>";
                            }
                            $html .= '</tbody></table>';
                            return new \Illuminate\Support\HtmlString($html);
                        }),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'confirmed',
                        'primary' => 'processing',
                        'secondary' => 'shipped',
                        'success' => 'delivered',
                        'danger' => 'cancelled',
                        'gray' => 'refunded',
                    ])
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Stars')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('export')
                        ->label('Export CSV')
                        ->icon('heroicon-m-document-arrow-down')
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records) {
                            $filename = 'orders-' . now()->format('Y-m-d-His') . '.csv';
                            $handle = fopen('php://temp', 'w+');
                            fputcsv($handle, ['Order #', 'Customer', 'Status', 'Stars', 'Items', 'Payment', 'Date']);

                            foreach ($records as $order) {
                                fputcsv($handle, [
                                    $order->order_number,
                                    $order->user?->name ?? 'N/A',
                                    $order->status,
                                    $order->total_amount,
                                    $order->items->count(),
                                    $order->payment_method ?? 'N/A',
                                    $order->created_at->format('Y-m-d H:i'),
                                ]);
                            }

                            rewind($handle);
                            $content = stream_get_contents($handle);
                            fclose($handle);

                            return response()->streamDownload(function () use ($content) {
                                echo $content;
                            }, $filename);
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
