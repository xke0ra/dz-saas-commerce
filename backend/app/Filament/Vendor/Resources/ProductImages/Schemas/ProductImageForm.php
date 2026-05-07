<?php

namespace App\Filament\Vendor\Resources\ProductImages\Schemas;

use App\Models\Product;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('tenant_id')
                    ->default(fn (): ?string => app(CurrentTenant::class)->id())
                    ->dehydrated(),
                Select::make('product_id')
                    ->options(function (): array {
                        $tenantId = app(CurrentTenant::class)->id();

                        if ($tenantId === null) {
                            return [];
                        }

                        return Product::query()
                            ->withoutGlobalScope('current_tenant')
                            ->where('tenant_id', $tenantId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
                FileUpload::make('path')
                    ->label('Image')
                    ->disk(config('commerce_media.product_images_disk', 'public'))
                    ->directory(fn (): string => 'tenant-products/'.(app(CurrentTenant::class)->id() ?? 'unassigned'))
                    ->visibility('public')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120)
                    ->downloadable()
                    ->openable()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('alt')
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_primary')
                    ->default(false)
                    ->required(),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
