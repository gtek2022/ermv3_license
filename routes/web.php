<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\License\InstallationController;
use App\Http\Controllers\License\LicenseCompanyController;
use App\Http\Controllers\Master\AppController;
use App\Http\Controllers\Master\CompanyController;
use App\Http\Controllers\Master\ConfigController;
use App\Http\Controllers\Master\FeatureFlagController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login'])->name('login.submit');
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// ── Authenticated area ────────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Master: Companies ─────────────────────────────────────────────────────
    Route::prefix('master/companies')->name('master.companies.')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::get('/create', [CompanyController::class, 'create'])->name('create');
        Route::post('/', [CompanyController::class, 'store'])->name('store');
        Route::get('/{hash}/edit', [CompanyController::class, 'edit'])->name('edit');
        Route::put('/{hash}', [CompanyController::class, 'update'])->name('update');
        Route::delete('/{hash}', [CompanyController::class, 'destroy'])->name('destroy');
        Route::get('/{hash}', [CompanyController::class, 'show'])->name('show');
    });

    // ── Master: Apps ──────────────────────────────────────────────────────────
    Route::prefix('master/apps')->name('master.apps.')->group(function () {
        Route::get('/', [AppController::class, 'index'])->name('index');
        Route::get('/create', [AppController::class, 'create'])->name('create');
        Route::post('/', [AppController::class, 'store'])->name('store');
        // Sub-routes before /{hash} wildcard
        Route::get('/{hash}/edit', [AppController::class, 'edit'])->name('edit');
        Route::put('/{hash}', [AppController::class, 'update'])->name('update');
        Route::delete('/{hash}', [AppController::class, 'destroy'])->name('destroy');
        Route::post('/{hash}/features', [AppController::class, 'storeFeature'])->name('features.store');
        Route::delete('/{hash}/features/{featureId}', [AppController::class, 'destroyFeature'])->name('features.destroy');
        Route::post('/{hash}/features/{featureId}/toggle', [AppController::class, 'toggleFeature'])->name('features.toggle');
        Route::get('/{hash}/features/{featureId}/retrieve-key', [AppController::class, 'retrieveFeatureKey'])->name('features.retrieve-key');
        Route::post('/{hash}/features/{featureId}/regenerate-key', [AppController::class, 'regenerateFeatureKey'])->name('features.regenerate-key');
        // Wildcard show LAST
        Route::get('/{hash}', [AppController::class, 'show'])->name('show');
    });

    // ── Master: Configs ───────────────────────────────────────────────────────
    Route::prefix('master/configs')->name('master.configs.')->group(function () {
        Route::get('/', [ConfigController::class, 'index'])->name('index');
        Route::get('/create', [ConfigController::class, 'create'])->name('create');
        Route::post('/', [ConfigController::class, 'store'])->name('store');
        Route::get('/{hash}/edit', [ConfigController::class, 'edit'])->name('edit');
        Route::put('/{hash}', [ConfigController::class, 'update'])->name('update');
        Route::get('/{hash}/history', [ConfigController::class, 'history'])->name('history');
        Route::post('/{hash}/rollback', [ConfigController::class, 'rollback'])->name('rollback');
        Route::delete('/{hash}', [ConfigController::class, 'destroy'])->name('destroy');
    });

    // ── Master: Feature Flags ─────────────────────────────────────────────────
    Route::prefix('master/feature-flags')->name('master.flags.')->group(function () {
        Route::get('/', [FeatureFlagController::class, 'index'])->name('index');
        Route::post('/', [FeatureFlagController::class, 'store'])->name('store');
        Route::post('/{hash}/toggle', [FeatureFlagController::class, 'toggle'])->name('toggle');
        Route::put('/{hash}', [FeatureFlagController::class, 'update'])->name('update');
        Route::delete('/{hash}', [FeatureFlagController::class, 'destroy'])->name('destroy');
    });

    // ── License: Companies (bundles) ──────────────────────────────────────────
    Route::prefix('licenses')->name('license.companies.')->group(function () {
        Route::get('/', [LicenseCompanyController::class, 'index'])->name('index');
        Route::get('/create', [LicenseCompanyController::class, 'create'])->name('create');
        Route::post('/', [LicenseCompanyController::class, 'store'])->name('store');
        // ── Sub-routes MUST come before /{hash} to avoid wildcard capture ──
        Route::get('/{hash}/retrieve-key', [LicenseCompanyController::class, 'retrieveKey'])->name('retrieve-key');
        Route::get('/{hash}/regenerate-confirm', [LicenseCompanyController::class, 'regenerateConfirm'])->name('regenerate-confirm');
        Route::post('/{hash}/regenerate-key', [LicenseCompanyController::class, 'regenerateKey'])->name('regenerate-key');
        Route::get('/{hash}/public-key', [LicenseCompanyController::class, 'publicKey'])->name('public-key');
        Route::get('/{hash}/delete-confirm', [LicenseCompanyController::class, 'deleteConfirm'])->name('delete-confirm');
        Route::post('/{hash}/revoke-all-usages', [LicenseCompanyController::class, 'revokeAllUsages'])->name('revoke-all-usages');
        Route::post('/{hash}/usages/{usageId}/revoke', [LicenseCompanyController::class, 'revokeUsage'])->name('usage.revoke');
        Route::delete('/{hash}', [LicenseCompanyController::class, 'destroy'])->name('destroy');
        Route::post('/{hash}/suspend', [LicenseCompanyController::class, 'suspend'])->name('suspend');
        Route::post('/{hash}/reinstate', [LicenseCompanyController::class, 'reinstate'])->name('reinstate');
        Route::post('/{hash}/cancel', [LicenseCompanyController::class, 'cancel'])->name('cancel');
        Route::post('/{hash}/renew', [LicenseCompanyController::class, 'renew'])->name('renew');
        Route::post('/{hash}/adjust-expiry', [LicenseCompanyController::class, 'adjustExpiry'])->name('adjust-expiry');
        Route::get('/{hash}/edit', [LicenseCompanyController::class, 'edit'])->name('edit');
        Route::put('/{hash}',      [LicenseCompanyController::class, 'update'])->name('update');
        Route::post('/{hash}/policy', [LicenseCompanyController::class, 'updatePolicy'])->name('policy.update');
        Route::post('/{hash}/features', [LicenseCompanyController::class, 'addFeature'])->name('features.add');
        Route::delete('/{hash}/features/{featureId}', [LicenseCompanyController::class, 'removeFeature'])->name('features.remove');
        Route::post('/{hash}/features/{featureId}/toggle', [LicenseCompanyController::class, 'toggleFeature'])->name('features.toggle');
        // ── Wildcard show route LAST ──────────────────────────────────────────
        Route::get('/{hash}', [LicenseCompanyController::class, 'show'])->name('show');
    });

    // ── License: Installations ────────────────────────────────────────────────
    Route::prefix('installations')->name('license.installations.')->group(function () {
        Route::get('/', [InstallationController::class, 'index'])->name('index');
        Route::get('/suspicious', [InstallationController::class, 'suspiciousIndex'])->name('suspicious.index');
        Route::post('/suspicious/ignore-all-global', [InstallationController::class, 'ignoreAllSuspiciousGlobal'])->name('suspicious.ignore-all-global');
        Route::get('/{hash}', [InstallationController::class, 'show'])->name('show');
        Route::post('/{hash}/revoke', [InstallationController::class, 'revoke'])->name('revoke');
        Route::post('/{hash}/blacklist', [InstallationController::class, 'blacklist'])->name('blacklist');
        Route::post('/{hash}/suspicious/{eventId}/review', [InstallationController::class, 'reviewSuspicious'])->name('suspicious.review');
        Route::post('/{hash}/suspicious/ignore-all', [InstallationController::class, 'ignoreAllSuspicious'])->name('suspicious.ignore-all');
    });

    // ── Users ─────────────────────────────────────────────────────────────────
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{hash}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{hash}', [UserManagementController::class, 'update'])->name('update');
        Route::delete('/{hash}', [UserManagementController::class, 'destroy'])->name('destroy');
    });

    // ── System: Public Key Info ───────────────────────────────────────────────
    Route::get('/system/public-key', [\App\Http\Controllers\Api\PublicKeyController::class, 'show'])
        ->name('system.public-key');

    // ── System: Heartbeat & Cron Setup Wizard ─────────────────────────────────
    Route::get('/system/heartbeat-setup', [\App\Http\Controllers\HeartbeatSetupController::class, 'show'])
        ->name('system.heartbeat-setup');
    Route::get('/system/heartbeat-setup/status', [\App\Http\Controllers\HeartbeatSetupController::class, 'status'])
        ->name('system.heartbeat-setup.status');
    Route::post('/system/heartbeat-setup/install', [\App\Http\Controllers\HeartbeatSetupController::class, 'installCron'])
        ->name('system.heartbeat-setup.install');
    Route::post('/system/heartbeat-setup/uninstall', [\App\Http\Controllers\HeartbeatSetupController::class, 'uninstallCron'])
        ->name('system.heartbeat-setup.uninstall');
    Route::post('/system/heartbeat-setup/test', [\App\Http\Controllers\HeartbeatSetupController::class, 'test'])
        ->name('system.heartbeat-setup.test');

    // ── Heartbeat Monitor (central view of all clients) ───────────────────────
    Route::get('/heartbeat-monitor', [\App\Http\Controllers\HeartbeatMonitorController::class, 'index'])
        ->name('heartbeat.monitor');
    Route::get('/heartbeat-monitor/data', [\App\Http\Controllers\HeartbeatMonitorController::class, 'data'])
        ->name('heartbeat.monitor.data');

    // ── Guide / Documentation ──────────────────────────────────────────────────
    Route::get('/guide/lisensi', [\App\Http\Controllers\GuideController::class, 'show'])
        ->name('guide.lisensi');
});
