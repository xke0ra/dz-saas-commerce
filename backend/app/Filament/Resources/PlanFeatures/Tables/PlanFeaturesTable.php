<?php

namespace App\Filament\Resources\PlanFeatures\Tables;

use App\Enums\PlanFeatureKey;
use App\Models\PlanFeature;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlanFeaturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plan.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->badge()
                    ->searchable(),
                TextColumn::make('value')
                    ->label('Value')
                    ->state(fn (PlanFeature $record): string => self::formatValue($record->normalizedValue())),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('key')
                    ->options(PlanFeatureKey::class)
                    ->searchable(),
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

    private static function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'Unlimited',
            is_bool($value) => $value ? 'Enabled' : 'Disabled',
            default => (string) $value,
        };
    }
}
