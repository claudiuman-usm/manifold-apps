<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

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
