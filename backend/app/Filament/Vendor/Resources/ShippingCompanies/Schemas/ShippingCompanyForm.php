<?php

namespace App\Filament\Vendor\Resources\ShippingCompanies\Schemas;

use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShippingCompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                TextInput::make('name')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('code')
                    ->maxLength(255)
                    ->alphaDash()
                    ->required(),
                TextInput::make('contact_phone')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('tracking_url_template')
                    ->helperText('Use {tracking_number} as the placeholder.')
                    ->maxLength(255),
                Toggle::make('supports_home_delivery')
                    ->default(true)
                    ->required(),
                Toggle::make('supports_desk_delivery')
                    ->default(true)
                    ->required(),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                KeyValue::make('settings')
                    ->columnSpanFull(),
            ]);
    }
}
