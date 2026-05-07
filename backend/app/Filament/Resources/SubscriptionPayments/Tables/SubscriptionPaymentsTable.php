<?php

namespace App\Filament\Resources\SubscriptionPayments\Tables;

use App\Actions\Billing\ConfirmSubscriptionPayment;
use App\Actions\Billing\RejectSubscriptionPayment;
use App\Enums\SubscriptionPaymentStatus;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['tenant', 'invoice', 'subscription.plan', 'confirmer', 'rejecter']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable(),
                TextColumn::make('tenant.name')
                    ->searchable(),
                TextColumn::make('subscription.plan.name')
                    ->label('Plan')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('method')
                    ->badge()
                    ->searchable(),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, SubscriptionPayment $record): string => number_format($state / 100, 2).' '.$record->currency)
                    ->sortable(),
                TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('proof_path')
                    ->label('Proof')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('confirmer.name')
                    ->label('Confirmed by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('confirmed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rejecter.name')
                    ->label('Rejected by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rejected_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rejection_reason')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SubscriptionPaymentStatus::class),
                SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(function (SubscriptionPayment $record): bool {
                        $user = auth()->user();

                        return $record->status === SubscriptionPaymentStatus::Pending
                            && $user instanceof User
                            && $user->can('confirm', $record);
                    })
                    ->action(function (SubscriptionPayment $record): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            abort(403);
                        }

                        app(ConfirmSubscriptionPayment::class)->handle($record, $user);

                        Notification::make()
                            ->success()
                            ->title('Subscription payment confirmed')
                            ->send();
                    }),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')
                            ->label('Rejection reason')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->visible(function (SubscriptionPayment $record): bool {
                        $user = auth()->user();

                        return $record->status === SubscriptionPaymentStatus::Pending
                            && $user instanceof User
                            && $user->can('reject', $record);
                    })
                    ->action(function (SubscriptionPayment $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            abort(403);
                        }

                        app(RejectSubscriptionPayment::class)->handle($record, $user, (string) $data['reason']);

                        Notification::make()
                            ->success()
                            ->title('Subscription payment rejected')
                            ->send();
                    }),
            ]);
    }
}
