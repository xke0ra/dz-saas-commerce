<?php

namespace App\Filament\Resources\PlanFeatures\Schemas;

use App\Enums\PlanFeatureKey;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class PlanFeatureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('plan_id')
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('key')
                    ->options(PlanFeatureKey::class)
                    ->searchable()
                    ->required(),
                KeyValue::make('value')
                    ->default(['value' => null])
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
