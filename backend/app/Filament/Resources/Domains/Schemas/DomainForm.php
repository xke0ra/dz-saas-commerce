<?php

namespace App\Filament\Resources\Domains\Schemas;

use App\Enums\DomainStatus;
use App\Models\Store;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DomainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Domain')
                    ->columns(2)
                    ->schema([
                        Select::make('store_id')
                            ->relationship(
                                'store',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->withoutGlobalScope('current_tenant')
                                    ->with('tenant')
                                    ->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Store $record): string => ($record->tenant?->name ?? 'Unknown tenant').' / '.$record->name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('hostname')
                            ->placeholder('shop.example.dz')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->required(),
                        Select::make('status')
                            ->options(DomainStatus::class)
                            ->default(DomainStatus::PendingVerification->value)
                            ->required(),
                        TextInput::make('verification_token')
                            ->maxLength(96)
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('verified_at'),
                        DateTimePicker::make('last_checked_at'),
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
