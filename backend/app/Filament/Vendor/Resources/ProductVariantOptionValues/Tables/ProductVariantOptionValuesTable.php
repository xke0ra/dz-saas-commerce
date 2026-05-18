<?php

namespace App\Filament\Vendor\Resources\ProductVariantOptionValues\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductVariantOptionValuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant.product.name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('variant.sku')
                    ->label('Variant SKU')
                    ->searchable(),
                TextColumn::make('variant.option_signature')
                    ->label('Variant')
                    ->searchable(),
                TextColumn::make('optionValue.option.name')
                    ->label('Option')
                    ->searchable(),
                TextColumn::make('optionValue.value')
                    ->label('Value')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
