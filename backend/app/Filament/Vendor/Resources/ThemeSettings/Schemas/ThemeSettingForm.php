<?php

namespace App\Filament\Vendor\Resources\ThemeSettings\Schemas;

use App\Models\Store;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ThemeSettingForm
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
                Section::make('Brand')
                    ->columns(2)
                    ->schema([
                        TextInput::make('theme_name')
                            ->default('default')
                            ->maxLength(255)
                            ->required(),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                        TextInput::make('logo_path')
                            ->maxLength(255),
                        TextInput::make('favicon_path')
                            ->maxLength(255),
                    ]),
                Section::make('Colors')
                    ->columns(4)
                    ->schema([
                        TextInput::make('primary_color')
                            ->type('color')
                            ->default('#107062')
                            ->required(),
                        TextInput::make('accent_color')
                            ->type('color')
                            ->default('#b54836')
                            ->required(),
                        TextInput::make('background_color')
                            ->type('color')
                            ->default('#f7f9fa')
                            ->required(),
                        TextInput::make('foreground_color')
                            ->type('color')
                            ->default('#161c24')
                            ->required(),
                    ]),
                Section::make('Hero')
                    ->columns(2)
                    ->schema([
                        TextInput::make('hero_title')
                            ->maxLength(255),
                        TextInput::make('hero_image_path')
                            ->maxLength(255),
                        Textarea::make('hero_subtitle')
                            ->columnSpanFull(),
                    ]),
                Section::make('Layout')
                    ->columns(2)
                    ->schema([
                        TextInput::make('heading_font')
                            ->maxLength(255),
                        TextInput::make('body_font')
                            ->maxLength(255),
                        Select::make('product_card_style')
                            ->options([
                                'standard' => 'Standard',
                                'compact' => 'Compact',
                            ])
                            ->default('standard')
                            ->required(),
                        KeyValue::make('layout_settings')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
