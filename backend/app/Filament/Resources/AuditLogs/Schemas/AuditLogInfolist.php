<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Audit event')
                    ->columns(3)
                    ->components([
                        TextEntry::make('event')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('id')
                            ->label('Audit ID')
                            ->copyable(),
                        TextEntry::make('tenant.name')
                            ->label('Tenant')
                            ->placeholder('Platform'),
                        TextEntry::make('actor.name')
                            ->label('Actor')
                            ->placeholder('System'),
                        TextEntry::make('ip_address')
                            ->label('IP address')
                            ->placeholder('-'),
                        TextEntry::make('auditable_type')
                            ->label('Target type')
                            ->formatStateUsing(fn (?string $state): ?string => $state ? class_basename($state) : null)
                            ->placeholder('-'),
                        TextEntry::make('auditable_id')
                            ->label('Target ID')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('user_agent')
                            ->label('User agent')
                            ->columnSpanFull()
                            ->placeholder('-'),
                    ]),
                Section::make('Change payload')
                    ->columns(3)
                    ->components([
                        CodeEntry::make('old_values')
                            ->label('Old values')
                            ->columnSpanFull()
                            ->copyable(),
                        CodeEntry::make('new_values')
                            ->label('New values')
                            ->columnSpanFull()
                            ->copyable(),
                        CodeEntry::make('metadata')
                            ->columnSpanFull()
                            ->copyable(),
                    ]),
            ]);
    }
}
