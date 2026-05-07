<?php

namespace App\Filament\Vendor\Resources\StoreSettings\Schemas;

use App\Models\Store;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StoreSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Section::make('Store')
                    ->schema([
                        Select::make('store_id')
                            ->options(fn () => Store::query()
                                ->forTenant(app(CurrentTenant::class)->id())
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record): bool => $record !== null)
                            ->dehydrated(),
                    ]),
                Section::make('Public identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('seller_name')
                            ->maxLength(255),
                        TextInput::make('seller_address')
                            ->maxLength(255),
                        TextInput::make('commercial_registration_number')
                            ->maxLength(255),
                        TextInput::make('tax_identification_number')
                            ->maxLength(255),
                        TextInput::make('public_email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('public_phone')
                            ->tel()
                            ->maxLength(32),
                        TextInput::make('support_phone')
                            ->tel()
                            ->maxLength(32),
                        TextInput::make('whatsapp_phone')
                            ->tel()
                            ->maxLength(32),
                    ]),
                Section::make('SEO and announcement')
                    ->columns(2)
                    ->schema([
                        TextInput::make('seo_title')
                            ->maxLength(255),
                        TextInput::make('announcement_text')
                            ->maxLength(255),
                        Textarea::make('seo_description')
                            ->columnSpanFull(),
                    ]),
                Section::make('Legal pages')
                    ->schema([
                        Textarea::make('terms_content')
                            ->rows(8)
                            ->columnSpanFull(),
                        Textarea::make('privacy_content')
                            ->rows(8)
                            ->columnSpanFull(),
                        Textarea::make('return_policy_content')
                            ->rows(8)
                            ->columnSpanFull(),
                        Textarea::make('shipping_policy_content')
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),
                KeyValue::make('social_links')
                    ->columnSpanFull(),
            ]);
    }
}
