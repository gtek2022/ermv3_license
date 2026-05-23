<?php

use App\Http\Controllers\Api\ConfigSyncController;
use App\Http\Controllers\Api\FeatureLicenseController;
use App\Http\Controllers\Api\LicensePolicyController;
use Illuminate\Support\Facades\Route;

/*
 | Custom API routes for gemilang.
 | The laravel-licensing package auto-registers its own routes under
 | api/licensing/v1 via LicensingServiceProvider.
 */

Route::prefix('api/licensing/v1')
    ->middleware(['api', 'throttle:api'])
    ->group(function () {
        // Heartbeat enforcement policy (tolerance + warning_days) per license
        Route::post('policy', [LicensePolicyController::class, 'show'])
            ->name('licensing.policy');
    });

Route::prefix('api/platform/v1')
    ->middleware(['api', 'throttle:api'])
    ->group(function () {
        // Config sync — ERMv3 fetches signed configs, feature flags, enforcement policy
        Route::post('config-sync', [ConfigSyncController::class, 'sync'])
            ->name('platform.config-sync');

        // Feature license — activate/deactivate individual feature licenses
        Route::post('feature/activate', [FeatureLicenseController::class, 'activate'])
            ->name('platform.feature.activate');
        Route::post('feature/deactivate', [FeatureLicenseController::class, 'deactivate'])
            ->name('platform.feature.deactivate');
        Route::post('feature/status', [FeatureLicenseController::class, 'status'])
            ->name('platform.feature.status');
    });
