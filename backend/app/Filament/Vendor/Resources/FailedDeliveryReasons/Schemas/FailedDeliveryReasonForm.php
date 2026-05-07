<?php

namespace App\Filament\Vendor\Resources\FailedDeliveryReasons\Schemas;

use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FailedDeliveryReasonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                TextInput::make('code')
                    ->maxLength(255)
                    ->alphaDash()
                    ->required(),
                TextInput::make('label_ar')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('label_fr')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
