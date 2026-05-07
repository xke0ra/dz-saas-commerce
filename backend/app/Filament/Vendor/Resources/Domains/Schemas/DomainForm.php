<?php

namespace App\Filament\Vendor\Resources\Domains\Schemas;

use App\Enums\DomainStatus;
use App\Models\Store;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DomainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Section::make('Custom domain')
                    ->columns(2)
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
                        TextInput::make('hostname')
                            ->placeholder('shop.example.dz')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->disabled(fn ($record): bool => $record !== null)
                            ->dehydrated(fn ($record): bool => $record === null),
                        Select::make('status')
                            ->options(DomainStatus::class)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('verification_token')
                            ->label('DNS TXT verification token')
                            ->disabled()
                            ->dehydrated(false),
                        Toggle::make('is_primary')
                            ->default(false),
                        Toggle::make('redirect_to_primary')
                            ->default(true),
                    ]),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
