<?php

namespace App\Filament\Vendor\Resources\Orders\Tables;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Actions\Orders\MarkOrderDelivered;
use App\Actions\Orders\MarkOrderOutForDelivery;
use App\Actions\Orders\PackOrder;
use App\Actions\Orders\ShipOrder;
use App\Actions\Orders\StartOrderProcessing;
use App\Actions\Orders\TransitionOrderStatus;
use App\Actions\Payments\MarkOrderPaymentFailed;
use App\Actions\Payments\RecordOrderPayment;
use App\Actions\Payments\RefundOrderPayment;
use App\Actions\Shipping\CreateShipmentForOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ShippingCompany;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('delivery_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('wilaya.name_fr')
                    ->label('Wilaya')
                    ->searchable(),
                TextColumn::make('commune.name_fr')
                    ->label('Commune')
                    ->searchable(),
                TextColumn::make('subtotal_minor')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2).' DZD')
                    ->sortable(),
                TextColumn::make('shipping_fee_minor')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2).' DZD')
                    ->sortable(),
                TextColumn::make('discount_minor')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2).' DZD')
                    ->sortable(),
                TextColumn::make('total_minor')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2).' DZD')
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('confirmed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class),
                SelectFilter::make('payment_status')
                    ->options(PaymentStatus::class),
            ])
            ->recordActions([
                Action::make('print_slip')
                    ->label('Slip')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Order $record): string => route('vendor.orders.slip', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Order $record): bool => self::canViewSlip($record)),
                Action::make('record_payment')
                    ->label('Record payment')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->schema([
                        Select::make('payment_method_id')
                            ->label('Payment method')
                            ->options(fn (Order $record): array => self::paymentMethodOptions($record))
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('amount_minor')
                            ->label('Amount minor')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn (Order $record): int => self::outstandingAmount($record))
                            ->required(),
                        TextInput::make('reference')
                            ->label('Reference')
                            ->maxLength(255),
                    ])
                    ->visible(fn (Order $record): bool => self::canRecordPayment($record))
                    ->action(function (Order $record, array $data): void {
                        $paymentMethod = PaymentMethod::query()
                            ->withoutGlobalScope('current_tenant')
                            ->where('tenant_id', $record->tenant_id)
                            ->whereKey($data['payment_method_id'])
                            ->firstOrFail();

                        app(RecordOrderPayment::class)->handle(
                            order: $record,
                            paymentMethod: $paymentMethod,
                            amountMinor: (int) $data['amount_minor'],
                            reference: filled($data['reference'] ?? null) ? (string) $data['reference'] : null,
                            metadata: [
                                'recorded_from' => 'vendor_panel',
                            ],
                        );

                        self::sendTransitionNotification('Payment recorded');
                    }),
                Action::make('payment_failed')
                    ->label('Payment failed')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')
                            ->label('Failure reason')
                            ->maxLength(1000),
                    ])
                    ->visible(fn (Order $record): bool => self::canFailPayment($record))
                    ->action(function (Order $record, array $data): void {
                        app(MarkOrderPaymentFailed::class)->handle(
                            $record,
                            filled($data['reason'] ?? null) ? (string) $data['reason'] : null,
                        );

                        self::sendTransitionNotification('Payment marked failed');
                    }),
                Action::make('refund_payment')
                    ->label('Refund')
                    ->icon(Heroicon::OutlinedReceiptRefund)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')
                            ->label('Refund reason')
                            ->maxLength(1000),
                    ])
                    ->visible(fn (Order $record): bool => self::canRefundPayment($record))
                    ->action(function (Order $record, array $data): void {
                        app(RefundOrderPayment::class)->handle(
                            $record,
                            filled($data['reason'] ?? null) ? (string) $data['reason'] : null,
                        );

                        self::sendTransitionNotification('Payment refunded');
                    }),
                Action::make('create_shipment')
                    ->label('Create shipment')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('primary')
                    ->schema([
                        Select::make('shipping_company_id')
                            ->label('Shipping company')
                            ->options(fn (Order $record): array => self::shippingCompanyOptions($record))
                            ->searchable()
                            ->preload(),
                        TextInput::make('tracking_number')
                            ->label('Tracking number')
                            ->maxLength(255),
                    ])
                    ->visible(fn (Order $record): bool => self::canCreateShipment($record))
                    ->action(function (Order $record, array $data): void {
                        $shippingCompany = filled($data['shipping_company_id'] ?? null)
                            ? ShippingCompany::query()
                                ->withoutGlobalScope('current_tenant')
                                ->where('tenant_id', $record->tenant_id)
                                ->whereKey($data['shipping_company_id'])
                                ->firstOrFail()
                            : null;

                        app(CreateShipmentForOrder::class)->handle(
                            $record,
                            $shippingCompany,
                            filled($data['tracking_number'] ?? null) ? (string) $data['tracking_number'] : null,
                        );

                        self::sendTransitionNotification('Shipment created');
                    }),
                Action::make('confirm')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => self::canTransition($record, OrderStatus::Confirmed, 'confirm'))
                    ->action(function (Order $record): void {
                        app(ConfirmOrder::class)->handle($record);

                        self::sendTransitionNotification('Order confirmed');
                    }),
                Action::make('process')
                    ->label('Process')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => self::canTransition($record, OrderStatus::Processing, 'ship'))
                    ->action(function (Order $record): void {
                        app(StartOrderProcessing::class)->handle($record);

                        self::sendTransitionNotification('Order moved to processing');
                    }),
                Action::make('pack')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => self::canTransition($record, OrderStatus::Packed, 'ship'))
                    ->action(function (Order $record): void {
                        app(PackOrder::class)->handle($record);

                        self::sendTransitionNotification('Order packed');
                    }),
                Action::make('ship')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => self::canTransition($record, OrderStatus::Shipped, 'ship'))
                    ->action(function (Order $record): void {
                        app(ShipOrder::class)->handle($record);

                        self::sendTransitionNotification('Order shipped');
                    }),
                Action::make('out_for_delivery')
                    ->label('Out for delivery')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => self::canTransition($record, OrderStatus::OutForDelivery, 'ship'))
                    ->action(function (Order $record): void {
                        app(MarkOrderOutForDelivery::class)->handle($record);

                        self::sendTransitionNotification('Order marked out for delivery');
                    }),
                Action::make('deliver')
                    ->icon(Heroicon::OutlinedHome)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => self::canTransition($record, OrderStatus::Delivered, 'ship'))
                    ->action(function (Order $record): void {
                        app(MarkOrderDelivered::class)->handle($record);

                        self::sendTransitionNotification('Order delivered');
                    }),
                Action::make('cancel')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => self::canTransition($record, OrderStatus::Cancelled, 'cancel'))
                    ->action(function (Order $record): void {
                        app(CancelOrder::class)->handle($record);

                        self::sendTransitionNotification('Order cancelled');
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function canViewSlip(Order $order): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('view', $order);
    }

    private static function canTransition(Order $order, OrderStatus $targetStatus, string $ability): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can($ability, $order)
            && app(TransitionOrderStatus::class)->canTransition($order->status, $targetStatus);
    }

    private static function sendTransitionNotification(string $title): void
    {
        Notification::make()
            ->success()
            ->title($title)
            ->send();
    }

    private static function canCreateShipment(Order $order): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('ship', $order)
            && in_array($order->status, [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Packed], true)
            && ! self::hasOpenShipment($order);
    }

    private static function canRecordPayment(Order $order): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('collectPayment', $order)
            && ! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true)
            && ! in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)
            && self::outstandingAmount($order) > 0;
    }

    private static function canFailPayment(Order $order): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('failPayment', $order)
            && ! in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)
            && $order->payments()
                ->where('status', PaymentStatus::Pending->value)
                ->exists();
    }

    private static function canRefundPayment(Order $order): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('refundPayment', $order)
            && $order->payment_status === PaymentStatus::Paid;
    }

    /**
     * @return array<string, string>
     */
    private static function paymentMethodOptions(Order $order): array
    {
        return PaymentMethod::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $order->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function outstandingAmount(Order $order): int
    {
        return app(RecordOrderPayment::class)->outstandingAmount($order);
    }

    /**
     * @return array<string, string>
     */
    private static function shippingCompanyOptions(Order $order): array
    {
        return ShippingCompany::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $order->tenant_id)
            ->where('is_active', true)
            ->when(
                $order->delivery_type->value === 'home',
                fn ($query) => $query->where('supports_home_delivery', true),
                fn ($query) => $query->where('supports_desk_delivery', true),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function hasOpenShipment(Order $order): bool
    {
        return $order->shipments()
            ->whereNotIn('status', [
                ShipmentStatus::Cancelled->value,
                ShipmentStatus::Returned->value,
            ])
            ->exists();
    }
}
