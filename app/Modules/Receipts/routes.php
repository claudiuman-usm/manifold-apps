<?php

use App\Modules\Receipts\Http\Controllers\ReceiptController;
use App\Modules\Receipts\Http\Controllers\ReceiptImageController;
use Illuminate\Support\Facades\Route;

/*
 * Receipts routes — loaded by ModuleServiceProvider with the "receipts" URL
 * prefix, "receipts." name prefix, and web + auth middleware.
 */

Route::get('/', [ReceiptController::class, 'index'])->name('index');
Route::get('create', [ReceiptController::class, 'create'])->name('create');
Route::post('/', [ReceiptController::class, 'store'])->name('store');
Route::get('{receipt}', [ReceiptController::class, 'show'])->name('show');
Route::put('{receipt}', [ReceiptController::class, 'update'])->name('update');
Route::delete('{receipt}', [ReceiptController::class, 'destroy'])->name('destroy');

Route::get('{receipt}/image/{variant?}', [ReceiptImageController::class, 'show'])->name('image');
