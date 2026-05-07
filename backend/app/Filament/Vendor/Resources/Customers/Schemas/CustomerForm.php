<?php

namespace App\Filament\Vendor\Resources\Customers\Schemas;

use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                TextInput::make('full_name')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(32)
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->maxLength(255)
                    ->email(),
                Select::make('wilaya_id')
                    ->relationship('wilaya', 'name_fr')
                    ->searchable()
                    ->preload(),
                Select::make('commune_id')
                    ->relationship('commune', 'name_fr')
                    ->searchable()
                    ->preload(),
                Textarea::make('address')
                    ->columnSpanFull(),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
