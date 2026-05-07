<?php

use App\Http\Controllers\Api\Storefront\GeographyController;
use App\Http\Controllers\Api\Storefront\StorefrontController;
use App\Support\System\SystemHealthChecker;
use Illuminate\Support\Facades\Route;

Route::get('system/health/live', function (SystemHealthChecker $healthChecker) {
    $report = $healthChecker->live();

    return response()->json($report, $report['status'] === 'ok' ? 200 : 503);
});

Route::get('system/health/ready', function (SystemHealthChecker $healthChecker) {
    $report = $healthChecker->ready();

    return response()->json($report, $report['status'] === 'ok' ? 200 : 503);
});

Route::prefix('storefront')
    ->middleware('throttle:120,1')
    ->group(function (): void {
        Route::get('geography/wilayas', [GeographyController::class, 'wilayas']);
        Route::get('geography/communes', [GeographyController::class, 'communes']);
        Route::get('resolve', [StorefrontController::class, 'resolve']);
        Route::get('{store}/home', [StorefrontController::class, 'home']);
        Route::get('{store}/products', [StorefrontController::class, 'products']);
        Route::get('{store}/products/{slug}', [StorefrontController::class, 'product']);
        Route::get('{store}/categories', [StorefrontController::class, 'categories']);
        Route::get('{store}/categories/{slug}', [StorefrontController::class, 'category']);
        Route::get('{store}/search', [StorefrontController::class, 'search']);
        Route::post('{store}/checkout', [StorefrontController::class, 'checkout'])->middleware('throttle:20,1');
        Route::get('{store}/track-order', [StorefrontController::class, 'trackOrder'])->middleware('throttle:60,1');
    });
