<?php

namespace App\Filament\Vendor\Resources\Subscriptions\Tables;

use App\Enums\SubscriptionStatus;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('plan'))
            ->defaultSort('is_current', 'desc')
            ->columns([
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_current')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('current_period_starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('current_period_ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('grace_ends_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SubscriptionStatus::class),
            ]);
    }
}
