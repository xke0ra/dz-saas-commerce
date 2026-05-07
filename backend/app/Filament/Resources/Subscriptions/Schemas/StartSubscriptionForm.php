<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StartSubscriptionForm
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
                Select::make('plan_id')
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->default(now())
                    ->required(),
                DateTimePicker::make('trial_ends_at'),
                Toggle::make('create_invoice')
                    ->default(true)
                    ->required(),
                TextInput::make('due_days')
                    ->numeric()
                    ->minValue(0)
                    ->default(7)
                    ->required(),
            ]);
    }
}
