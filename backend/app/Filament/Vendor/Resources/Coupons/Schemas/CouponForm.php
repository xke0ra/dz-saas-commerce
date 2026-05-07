<?php

namespace App\Filament\Vendor\Resources\Coupons\Schemas;

use App\Enums\CouponType;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Section::make('Coupon')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->maxLength(64)
                            ->alphaDash()
                            ->required(),
                        TextInput::make('name')
                            ->maxLength(255),
                        Select::make('type')
                            ->options(CouponType::class)
                            ->default(CouponType::FixedAmount->value)
                            ->required(),
                        TextInput::make('value')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('max_discount_minor')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('minimum_subtotal_minor')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ]),
                Section::make('Limits and availability')
                    ->columns(2)
                    ->schema([
                        TextInput::make('usage_limit')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('used_count')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('starts_at'),
                        DateTimePicker::make('ends_at'),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ]),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
