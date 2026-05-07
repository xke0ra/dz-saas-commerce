<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Enums\SubscriptionStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubscriptionForm
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
                Select::make('status')
                    ->options(SubscriptionStatus::class)
                    ->required(),
                Toggle::make('is_current')
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->required(),
                DateTimePicker::make('trial_ends_at'),
                DateTimePicker::make('current_period_starts_at')
                    ->required(),
                DateTimePicker::make('current_period_ends_at')
                    ->required(),
                DateTimePicker::make('grace_ends_at'),
                DateTimePicker::make('cancelled_at'),
                DateTimePicker::make('ends_at'),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
