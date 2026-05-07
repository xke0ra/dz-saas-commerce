<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('subscription_id')
                    ->relationship('subscription', 'id')
                    ->searchable()
                    ->preload(),
                TextInput::make('invoice_number')
                    ->maxLength(255)
                    ->required(),
                Select::make('type')
                    ->options(InvoiceType::class)
                    ->required(),
                Select::make('status')
                    ->options(InvoiceStatus::class)
                    ->required(),
                TextInput::make('subtotal_minor')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('tax_minor')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('total_minor')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('paid_amount_minor')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('balance_minor')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('currency')
                    ->length(3)
                    ->required()
                    ->default('DZD'),
                DateTimePicker::make('issued_at'),
                DateTimePicker::make('due_at'),
                DateTimePicker::make('paid_at'),
                DateTimePicker::make('billing_period_starts_at'),
                DateTimePicker::make('billing_period_ends_at'),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
