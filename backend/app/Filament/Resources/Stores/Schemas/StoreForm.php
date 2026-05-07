<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Enums\StoreStatus;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StoreForm
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
                TextInput::make('name')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('slug')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('domain')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('subdomain')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('status')
                    ->options(StoreStatus::class)
                    ->default(StoreStatus::Draft->value)
                    ->required(),
                TextInput::make('locale')
                    ->maxLength(5)
                    ->required()
                    ->default('ar'),
                TextInput::make('currency')
                    ->length(3)
                    ->required()
                    ->default('DZD'),
                KeyValue::make('settings')
                    ->columnSpanFull(),
            ]);
    }
}
