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
        Route::get('/{hash}', [CompanyController::class, 'show'])->name('show');
        Route::get('/{hash}/edit', [CompanyController::class, 'edit'])->name('edit');
        Route::put('/{hash}', [CompanyController::class, 'update'])->name('update');
        Route::delete('/{hash}', [CompanyController::class, 'destroy'])->name('destroy');
    });

    // ── Master: Apps ──────────────────────────────────────────────────────────
    Route::prefix('master/apps')->name('master.apps.')->group(function () {
        Route::get('/', [AppController::class, 'index'])->name('index');
        Route::get('/create', [AppController::class, 'create'])->name('create');
        Route::post('/', [AppController::class, 'store'])->name('store');
        Route::get('/{hash}', [AppController::class, 'show'])->name('show');
        Route::get('/{hash}/edit', [AppController::class, 'edit'])->name('edit');
        Route::put('/{hash}', [AppController::class, 'update'])->name('update');
        Route::post('/{hash}/features', [AppController::class, 'storeFeature'])->name('features.store');
        Route::delete('/{hash}/features/{featureId}', [AppController::class, 'destroyFeature'])->name('features.destroy');
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
        Route::get('/{hash}', [LicenseCompanyController::class, 'show'])->name('show');
        Route::post('/{hash}/suspend', [LicenseCompanyController::class, 'suspend'])->name('suspend');
        Route::post('/{hash}/reinstate', [LicenseCompanyController::class, 'reinstate'])->name('reinstate');
        Route::post('/{hash}/cancel', [LicenseCompanyController::class, 'cancel'])->name('cancel');
        Route::post('/{hash}/renew', [LicenseCompanyController::class, 'renew'])->name('renew');
        Route::post('/{hash}/policy', [LicenseCompanyController::class, 'updatePolicy'])->name('policy.update');
        Route::get('/{hash}/retrieve-key', [LicenseCompanyController::class, 'retrieveKey'])->name('retrieve-key');
        Route::post('/{hash}/regenerate-key', [LicenseCompanyController::class, 'regenerateKey'])->name('regenerate-key');
    });

    // ── License: Installations ────────────────────────────────────────────────
    Route::prefix('installations')->name('license.installations.')->group(function () {
        Route::get('/', [InstallationController::class, 'index'])->name('index');
        Route::get('/{hash}', [InstallationController::class, 'show'])->name('show');
        Route::post('/{hash}/revoke', [InstallationController::class, 'revoke'])->name('revoke');
        Route::post('/{hash}/blacklist', [InstallationController::class, 'blacklist'])->name('blacklist');
        Route::post('/{hash}/suspicious/{eventId}/review', [InstallationController::class, 'reviewSuspicious'])->name('suspicious.review');
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
});
