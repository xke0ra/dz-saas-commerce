<?php

namespace App\Filament\Vendor\Resources\OrderReturns\Schemas;

use App\Enums\OrderReturnStatus;
use App\Models\OrderReturn;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderReturnForm
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
                Select::make('customer_id')
                    ->relationship('customer', 'full_name')
                    ->searchable()
                    ->preload(),
                TextInput::make('return_number')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('status')
                    ->options(OrderReturnStatus::class)
                    ->disabled(fn (?OrderReturn $record): bool => $record !== null)
                    ->dehydrated(fn (?OrderReturn $record): bool => $record === null)
                    ->default(OrderReturnStatus::Requested->value)
                    ->required(),
                Textarea::make('reason')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('resolution_note')
                    ->columnSpanFull(),
                DateTimePicker::make('requested_at')
                    ->default(now()),
                DateTimePicker::make('resolved_at')
                    ->disabled()
                    ->dehydrated(false),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
