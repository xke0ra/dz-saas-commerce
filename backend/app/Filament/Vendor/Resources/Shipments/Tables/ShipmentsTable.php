<?php

namespace App\Filament\Vendor\Resources\Shipments\Tables;

use App\Actions\Shipping\CancelShipment;
use App\Actions\Shipping\MarkShipmentDelivered;
use App\Actions\Shipping\MarkShipmentFailed;
use App\Actions\Shipping\MarkShipmentInTransit;
use App\Actions\Shipping\MarkShipmentOutForDelivery;
use App\Actions\Shipping\MarkShipmentReturned;
use App\Actions\Shipping\MarkShipmentShipped;
use App\Actions\Shipping\RetryShipmentDelivery;
use App\Actions\Shipping\TransitionShipmentStatus;
use App\Enums\ShipmentStatus;
use App\Models\FailedDeliveryReason;
use App\Models\Shipment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShipmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('shippingCompany.name')
                    ->label('Company')
                    ->searchable(),
                TextColumn::make('tracking_number')
                    ->searchable(),
                TextColumn::make('status')
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
                TextColumn::make('shipping_fee_minor')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2).' DZD')
                    ->sortable(),
                TextColumn::make('shipped_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('delivered_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ShipmentStatus::class),
            ])
            ->recordActions([
                Action::make('tracking')
                    ->label('Track')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->url(fn (Shipment $record): ?string => $record->trackingUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Shipment $record): bool => $record->trackingUrl() !== null),
                Action::make('ship')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Shipment $record): bool => self::canTransition($record, ShipmentStatus::Shipped))
                    ->action(function (Shipment $record): void {
                        app(MarkShipmentShipped::class)->handle($record);

                        self::sendTransitionNotification('Shipment marked shipped');
                    }),
                Action::make('in_transit')
                    ->label('In transit')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Shipment $record): bool => self::canTransition($record, ShipmentStatus::InTransit))
                    ->action(function (Shipment $record): void {
                        app(MarkShipmentInTransit::class)->handle($record);

                        self::sendTransitionNotification('Shipment marked in transit');
                    }),
                Action::make('out_for_delivery')
                    ->label('Out for delivery')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Shipment $record): bool => self::canTransition($record, ShipmentStatus::OutForDelivery))
                    ->action(function (Shipment $record): void {
                        app(MarkShipmentOutForDelivery::class)->handle($record);

                        self::sendTransitionNotification('Shipment marked out for delivery');
                    }),
                Action::make('deliver')
                    ->icon(Heroicon::OutlinedHome)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Shipment $record): bool => self::canTransition($record, ShipmentStatus::Delivered))
                    ->action(function (Shipment $record): void {
                        app(MarkShipmentDelivered::class)->handle($record);

                        self::sendTransitionNotification('Shipment delivered');
                    }),
                Action::make('failed')
                    ->label('Failed')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Select::make('failed_delivery_reason_id')
                            ->label('Failed delivery reason')
                            ->options(fn (Shipment $record): array => self::failedDeliveryReasonOptions($record))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('failure_note')
                            ->label('Failure note')
                            ->maxLength(1000),
                    ])
                    ->visible(fn (Shipment $record): bool => self::canTransition($record, ShipmentStatus::FailedDelivery))
                    ->action(function (Shipment $record, array $data): void {
                        $reason = FailedDeliveryReason::query()
                            ->withoutGlobalScope('current_tenant')
                            ->where('tenant_id', $record->tenant_id)
                            ->whereKey($data['failed_delivery_reason_id'])
                            ->firstOrFail();

                        app(MarkShipmentFailed::class)->handle(
                            $record,
                            $reason,
                            filled($data['failure_note'] ?? null) ? (string) $data['failure_note'] : null,
                        );

                        self::sendTransitionNotification('Shipment marked failed');
                    }),
                Action::make('retry_delivery')
                    ->label('Retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Shipment $record): bool => self::canTransition($record, ShipmentStatus::OutForDelivery))
                    ->action(function (Shipment $record): void {
                        app(RetryShipmentDelivery::class)->handle($record);

                        self::sendTransitionNotification('Shipment retry started');
                    }),
                Action::make('return_to_sender')
                    ->label('Returned')
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Shipment $record): bool => self::canTransition($record, ShipmentStatus::Returned))
                    ->action(function (Shipment $record): void {
                        app(MarkShipmentReturned::class)->handle($record);

                        self::sendTransitionNotification('Shipment returned');
                    }),
                Action::make('cancel')
                    ->icon(Heroicon::OutlinedArchiveBoxXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Shipment $record): bool => self::canTransition($record, ShipmentStatus::Cancelled))
                    ->action(function (Shipment $record): void {
                        app(CancelShipment::class)->handle($record);

                        self::sendTransitionNotification('Shipment cancelled');
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function canTransition(Shipment $shipment, ShipmentStatus $targetStatus): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('update', $shipment)
            && app(TransitionShipmentStatus::class)->canTransition($shipment->status, $targetStatus);
    }

    /**
     * @return array<string, string>
     */
    private static function failedDeliveryReasonOptions(Shipment $shipment): array
    {
        return FailedDeliveryReason::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $shipment->tenant_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label_fr')
            ->pluck('label_fr', 'id')
            ->all();
    }

    private static function sendTransitionNotification(string $title): void
    {
        Notification::make()
            ->success()
            ->title($title)
            ->send();
    }
}
