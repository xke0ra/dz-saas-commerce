<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SupportPanelProvider;
use App\Providers\Filament\VendorPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SupportPanelProvider::class,
    VendorPanelProvider::class,
];
