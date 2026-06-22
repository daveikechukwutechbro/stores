<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralLabel = 'Products';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('stock', '<=', 5)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('stock', '<=', 5)->count() > 0 ? 'warning' : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('slug')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                Forms\Components\TextInput::make('compare_price')
                    ->numeric(),
                Forms\Components\FileUpload::make('image')
                    ->image(),
                Forms\Components\Textarea::make('images')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Select::make('color')
                    ->options([
                        'Red' => 'Red',
                        'Blue' => 'Blue',
                        'Green' => 'Green',
                        'Black' => 'Black',
                        'White' => 'White',
                        'Gray' => 'Gray',
                        'Yellow' => 'Yellow',
                        'Purple' => 'Purple',
                        'Orange' => 'Orange',
                        'Pink' => 'Pink',
                        'Brown' => 'Brown',
                        'Navy' => 'Navy',
                        'Teal' => 'Teal',
                        'Maroon' => 'Maroon',
                    ])
                    ->searchable()
                    ->native(false)
                    ->placeholder('Select a color'),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                Forms\Components\Toggle::make('featured')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\TextInput::make('rating')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('review_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('compare_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('image')
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false)
                    ->extraImgAttributes(['class' => 'cursor-pointer']),
                Tables\Columns\TextColumn::make('stock')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state): string => $state === 0 ? 'danger' : ($state <= 5 ? 'warning' : 'gray'))
                    ->badge(),
                Tables\Columns\TextColumn::make('color')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Red' => 'danger',
                        'Blue' => 'info',
                        'Green' => 'success',
                        'Black' => 'gray',
                        'White' => 'gray',
                        'Gray' => 'gray',
                        'Yellow' => 'warning',
                        'Purple' => 'purple',
                        'Orange' => 'warning',
                        'Pink' => 'pink',
                        'Brown' => 'gray',
                        'Navy' => 'info',
                        'Teal' => 'success',
                        'Maroon' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('category.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('featured')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('rating')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('review_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stock_status')
                    ->label('Stock')
                    ->options([
                        'low' => 'Low Stock (≤5)',
                        'out' => 'Out of Stock',
                        'in' => 'In Stock',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value'] ?? null) {
                            'low' => $query->where('stock', '>', 0)->where('stock', '<=', 5),
                            'out' => $query->where('stock', 0),
                            'in' => $query->where('stock', '>', 5),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
