<?php

namespace App\Filament\Vendor\Resources\Shipments\Schemas;

use App\Enums\DeliveryType;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ShipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Select::make('order_id')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('shipping_company_id')
                    ->relationship('shippingCompany', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('tracking_number')
                    ->maxLength(255),
                Select::make('status')
                    ->options(ShipmentStatus::class)
                    ->disabled(fn (?Shipment $record): bool => $record !== null)
                    ->dehydrated(fn (?Shipment $record): bool => $record === null)
                    ->default(ShipmentStatus::Pending->value)
                    ->required(),
                Select::make('delivery_type')
                    ->options(DeliveryType::class)
                    ->default(DeliveryType::Home->value)
                    ->required(),
                Select::make('wilaya_id')
                    ->relationship('wilaya', 'name_fr')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('commune_id')
                    ->relationship('commune', 'name_fr')
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('destination_address')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('shipping_fee_minor')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                TextInput::make('currency')
                    ->length(3)
                    ->default('DZD')
                    ->required(),
                DateTimePicker::make('shipped_at')
                    ->disabled()
                    ->dehydrated(false),
                DateTimePicker::make('delivered_at')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('failed_delivery_reason_id')
                    ->relationship('failedDeliveryReason', 'label_fr')
                    ->searchable()
                    ->preload(),
                Textarea::make('failure_note')
                    ->columnSpanFull(),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
