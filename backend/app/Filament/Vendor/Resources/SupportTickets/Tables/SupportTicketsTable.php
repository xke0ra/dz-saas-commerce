<?php

namespace App\Filament\Vendor\Resources\SupportTickets\Tables;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['store', 'assignedTo']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(56),
                TextColumn::make('store.name')
                    ->label('Store')
                    ->placeholder('Tenant level'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SupportTicketStatus::class),
                SelectFilter::make('priority')
                    ->options(SupportTicketPriority::class),
                SelectFilter::make('category')
                    ->options(SupportTicketCategory::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
