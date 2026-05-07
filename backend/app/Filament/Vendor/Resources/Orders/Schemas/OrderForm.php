<?php

namespace App\Filament\Vendor\Resources\Orders\Schemas;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Select::make('store_id')
                    ->relationship('store', 'name')
                    ->disabled()
                    ->dehydrated(false)
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'full_name')
                    ->disabled()
                    ->dehydrated(false)
                    ->required(),
                TextInput::make('order_number')
                    ->disabled()
                    ->dehydrated(false)
                    ->required(),
                Select::make('status')
                    ->options(OrderStatus::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->default(OrderStatus::Pending->value)
                    ->required(),
                Select::make('payment_status')
                    ->options(PaymentStatus::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->default(PaymentStatus::Unpaid->value)
                    ->required(),
                Select::make('delivery_type')
                    ->options(DeliveryType::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->default(DeliveryType::Home->value)
                    ->required(),
                Select::make('wilaya_id')
                    ->relationship('wilaya', 'name_fr')
                    ->disabled()
                    ->dehydrated(false)
                    ->required(),
                Select::make('commune_id')
                    ->relationship('commune', 'name_fr')
                    ->disabled()
                    ->dehydrated(false)
                    ->required(),
                Textarea::make('shipping_address')
                    ->disabled()
                    ->dehydrated(false)
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('customer_note')
                    ->columnSpanFull(),
                TextInput::make('subtotal_minor')
                    ->disabled()
                    ->dehydrated(false)
                    ->required()
                    ->numeric(),
                TextInput::make('shipping_fee_minor')
                    ->disabled()
                    ->dehydrated(false)
                    ->required()
                    ->numeric(),
                TextInput::make('discount_minor')
                    ->disabled()
                    ->dehydrated(false)
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_minor')
                    ->disabled()
                    ->dehydrated(false)
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->disabled()
                    ->dehydrated(false)
                    ->required()
                    ->default('DZD'),
                DateTimePicker::make('confirmed_at')
                    ->disabled()
                    ->dehydrated(false),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
