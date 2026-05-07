<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\Store;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ticket')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ticket_number')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('tenant_id')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('store_id')
                            ->options(fn (): array => Store::query()
                                ->withoutGlobalScope('current_tenant')
                                ->with('tenant')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Store $store): array => [
                                    $store->id => ($store->tenant?->name ?? 'Unknown tenant').' / '.$store->name,
                                ])
                                ->all())
                            ->searchable()
                            ->preload(),
                        Select::make('requester_id')
                            ->label('Requester')
                            ->options(fn (): array => User::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload(),
                        TextInput::make('subject')
                            ->maxLength(255)
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        Select::make('category')
                            ->options(SupportTicketCategory::class)
                            ->default(SupportTicketCategory::General->value)
                            ->required(),
                        Select::make('priority')
                            ->options(SupportTicketPriority::class)
                            ->default(SupportTicketPriority::Normal->value)
                            ->required(),
                        Select::make('status')
                            ->options(SupportTicketStatus::class)
                            ->default(SupportTicketStatus::Open->value)
                            ->required(),
                        Select::make('assigned_to_id')
                            ->label('Assigned to')
                            ->options(fn (): array => User::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make('Resolution')
                    ->columns(2)
                    ->schema([
                        Textarea::make('resolution')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('internal_notes')
                            ->rows(4)
                            ->columnSpanFull(),
                        DateTimePicker::make('last_response_at'),
                        DateTimePicker::make('resolved_at'),
                        DateTimePicker::make('closed_at'),
                    ]),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
