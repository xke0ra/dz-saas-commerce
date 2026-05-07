<?php

namespace App\Filament\Vendor\Resources\OrderReturns\Tables;

use App\Actions\Returns\ApproveOrderReturn;
use App\Actions\Returns\CancelOrderReturn;
use App\Actions\Returns\ReceiveOrderReturn;
use App\Actions\Returns\RefundOrderReturn;
use App\Actions\Returns\RejectOrderReturn;
use App\Actions\Returns\TransitionOrderReturnStatus;
use App\Enums\OrderReturnStatus;
use App\Enums\PaymentStatus;
use App\Models\OrderReturn;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_number')
                    ->searchable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('requested_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderReturnStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('resolution_note')
                            ->label('Resolution note')
                            ->maxLength(1000),
                    ])
                    ->visible(fn (OrderReturn $record): bool => self::canTransition($record, OrderReturnStatus::Approved, 'approve'))
                    ->action(function (OrderReturn $record, array $data): void {
                        app(ApproveOrderReturn::class)->handle(
                            $record,
                            filled($data['resolution_note'] ?? null) ? (string) $data['resolution_note'] : null,
                        );

                        self::sendReturnNotification('Return approved');
                    }),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('resolution_note')
                            ->label('Resolution note')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->visible(fn (OrderReturn $record): bool => self::canTransition($record, OrderReturnStatus::Rejected, 'reject'))
                    ->action(function (OrderReturn $record, array $data): void {
                        app(RejectOrderReturn::class)->handle($record, (string) $data['resolution_note']);

                        self::sendReturnNotification('Return rejected');
                    }),
                Action::make('receive')
                    ->label('Receive')
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->color('info')
                    ->requiresConfirmation()
                    ->schema([
                        Toggle::make('restock')
                            ->label('Restock returned items')
                            ->default(true),
                        Textarea::make('resolution_note')
                            ->label('Resolution note')
                            ->maxLength(1000),
                    ])
                    ->visible(fn (OrderReturn $record): bool => self::canTransition($record, OrderReturnStatus::Received, 'receive'))
                    ->action(function (OrderReturn $record, array $data): void {
                        app(ReceiveOrderReturn::class)->handle(
                            orderReturn: $record,
                            restock: (bool) ($data['restock'] ?? true),
                            resolutionNote: filled($data['resolution_note'] ?? null) ? (string) $data['resolution_note'] : null,
                        );

                        self::sendReturnNotification('Return received');
                    }),
                Action::make('refund')
                    ->icon(Heroicon::OutlinedReceiptRefund)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('resolution_note')
                            ->label('Refund note')
                            ->maxLength(1000),
                    ])
                    ->visible(fn (OrderReturn $record): bool => self::canRefund($record))
                    ->action(function (OrderReturn $record, array $data): void {
                        app(RefundOrderReturn::class)->handle(
                            $record,
                            filled($data['resolution_note'] ?? null) ? (string) $data['resolution_note'] : null,
                        );

                        self::sendReturnNotification('Return refunded');
                    }),
                Action::make('cancel')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('resolution_note')
                            ->label('Cancellation note')
                            ->maxLength(1000),
                    ])
                    ->visible(fn (OrderReturn $record): bool => self::canTransition($record, OrderReturnStatus::Cancelled, 'cancel'))
                    ->action(function (OrderReturn $record, array $data): void {
                        app(CancelOrderReturn::class)->handle(
                            $record,
                            filled($data['resolution_note'] ?? null) ? (string) $data['resolution_note'] : null,
                        );

                        self::sendReturnNotification('Return cancelled');
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function canTransition(OrderReturn $orderReturn, OrderReturnStatus $targetStatus, string $ability): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can($ability, $orderReturn)
            && app(TransitionOrderReturnStatus::class)->canTransition($orderReturn->status, $targetStatus);
    }

    private static function canRefund(OrderReturn $orderReturn): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('refund', $orderReturn)
            && app(TransitionOrderReturnStatus::class)->canTransition($orderReturn->status, OrderReturnStatus::Refunded)
            && $orderReturn->order()->where('payment_status', PaymentStatus::Paid->value)->exists();
    }

    private static function sendReturnNotification(string $title): void
    {
        Notification::make()
            ->success()
            ->title($title)
            ->send();
    }
}
