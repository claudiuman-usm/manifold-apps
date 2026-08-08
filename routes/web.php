<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\IconController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

// Stylesheet served through the app so `git pull` alone updates it (no asset
// copy into the shim's served folder). Public — the login page needs it.
Route::get('assets/app.css', [AssetController::class, 'css'])->name('assets.css');

// Home-screen / touch icons + web manifest (public — needed on the login page).
Route::get('assets/icon/{size}.png', [IconController::class, 'icon'])->name('assets.icon')->whereNumber('size');
Route::get('manifest.webmanifest', [IconController::class, 'manifest'])->name('assets.manifest');

// No-SSH migration runner (token-guarded; see deploy/DEPLOY.md).
Route::get('_deploy/migrate', [DeployController::class, 'migrate'])->name('deploy.migrate');

// Locale + theme switches (available to guests too, so the login screen can toggle).
Route::get('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
Route::get('theme/{theme}', [ThemeController::class, 'switch'])->name('theme.switch');

// Auth gate — single user, no registration.
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Hub.
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});
