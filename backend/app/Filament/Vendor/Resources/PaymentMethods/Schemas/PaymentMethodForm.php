<?php

namespace App\Filament\Vendor\Resources\PaymentMethods\Schemas;

use App\Enums\PaymentMethodType;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Select::make('type')
                    ->options(PaymentMethodType::class)
                    ->default(PaymentMethodType::CashOnDelivery->value)
                    ->required(),
                TextInput::make('name')
                    ->maxLength(255)
                    ->required(),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                Textarea::make('instructions')
                    ->columnSpanFull(),
                KeyValue::make('settings')
                    ->columnSpanFull(),
            ]);
    }
}
