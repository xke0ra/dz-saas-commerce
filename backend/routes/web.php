<?php

use App\Http\Controllers\Vendor\OrderSlipController;
use App\Http\Controllers\Vendor\SwitchTenantController;
use App\Http\Controllers\Vendor\TenantInvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/vendor/invitations/{token}/accept', [TenantInvitationController::class, 'accept'])
    ->name('vendor.invitations.accept');

Route::middleware('auth')->get('/vendor/orders/{order}/slip', OrderSlipController::class)
    ->name('vendor.orders.slip');

Route::middleware(['auth', 'throttle:30,1'])->post('/vendor/switch-tenant', SwitchTenantController::class)
    ->name('vendor.tenants.switch');
