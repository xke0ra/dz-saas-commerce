<?php

namespace App\Filament\Vendor\Resources\Domains\Tables;

use App\Actions\Domains\VerifyDomainOwnership;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('hostname')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('store.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                IconColumn::make('is_primary')
                    ->boolean(),
                TextColumn::make('verification_token')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(DomainStatus::class),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Verify DNS')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(function (Domain $record): bool {
                        $user = auth()->user();

                        return $record->status !== DomainStatus::Disabled
                            && $user instanceof User
                            && $user->can('update', $record);
                    })
                    ->action(function (Domain $record): void {
                        $domain = app(VerifyDomainOwnership::class)->handle($record);

                        Notification::make()
                            ->title($domain->isResolvable() ? 'Domain verified' : 'DNS verification record not found')
                            ->body($domain->isResolvable()
                                ? 'The custom domain is now active.'
                                : 'Add the TXT verification record, then run verification again.')
                            ->status($domain->isResolvable() ? 'success' : 'warning')
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
