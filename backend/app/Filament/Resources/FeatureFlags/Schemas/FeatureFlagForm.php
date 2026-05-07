<?php

namespace App\Filament\Resources\FeatureFlags\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FeatureFlagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('name')
                    ->maxLength(255)
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_enabled')
                    ->default(false)
                    ->required(),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
