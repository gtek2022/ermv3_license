<?php

use App\Http\Controllers\Api\ConfigSyncController;
use App\Http\Controllers\Api\FeatureLicenseController;
use App\Http\Controllers\Api\LicensePolicyController;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | API Routes
 |--------------------------------------------------------------------------
 | Laravel 12's bootstrap/app.php (withRouting) already prefixes all routes
 | in this file with `api/`, so we MUST NOT prefix routes here with `api/`
 | again — otherwise URLs become /api/api/platform/v1/... which 404s.
 |
 | Final URLs:
 |   POST  /api/licensing/v1/policy
 |   GET   /api/platform/v1/public-key
 |   POST  /api/platform/v1/config-sync
 |   POST  /api/platform/v1/feature/{activate|deactivate|status}
 |
 | The masterix21/laravel-licensing package self-registers routes under
 | /api/licensing/v1/* (activate, refresh, heartbeat, validate, etc).
 */

Route::prefix('licensing/v1')
    ->middleware('throttle:api')
    ->group(function () {
        Route::post('policy', [LicensePolicyController::class, 'show'])
            ->name('licensing.policy');
    });

Route::prefix('platform/v1')
    ->middleware('throttle:api')
    ->group(function () {
        // Public key endpoint — no auth, used by client apps during install
        Route::get('public-key', [\App\Http\Controllers\Api\PublicKeyController::class, 'show'])
            ->name('platform.public-key');

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
