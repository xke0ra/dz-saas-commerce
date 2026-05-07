<?php

namespace App\Filament\Vendor\Resources\SupportTickets\Schemas;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\Store;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Hidden;
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
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Hidden::make('requester_id')
                    ->default(fn (): ?int => auth()->id())
                    ->dehydrated(),
                Section::make('Ticket')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ticket_number')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('store_id')
                            ->options(fn (): array => Store::query()
                                ->forTenant(app(CurrentTenant::class)->id())
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
                    ]),
                Section::make('Response')
                    ->schema([
                        Textarea::make('resolution')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
