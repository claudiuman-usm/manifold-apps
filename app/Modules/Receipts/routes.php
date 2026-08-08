<?php

use App\Modules\Receipts\Http\Controllers\AllocationController;
use App\Modules\Receipts\Http\Controllers\ClientController;
use App\Modules\Receipts\Http\Controllers\ReceiptController;
use App\Modules\Receipts\Http\Controllers\ReceiptImageController;
use Illuminate\Support\Facades\Route;

/*
 * Receipts routes — loaded by ModuleServiceProvider with the "receipts" URL
 * prefix, "receipts." name prefix, and web + auth middleware.
 * Literal paths are declared BEFORE the {receipt} wildcard so they win.
 */

Route::get('/', [ReceiptController::class, 'index'])->name('index');
Route::get('create', [ReceiptController::class, 'create'])->name('create');
Route::get('export', [ReceiptController::class, 'export'])->name('export');
Route::post('/', [ReceiptController::class, 'store'])->name('store');

// Clients.
Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

// Allocations (invoice grouping per client).
Route::get('allocations', [AllocationController::class, 'index'])->name('allocations.index');
Route::get('allocations/create', [AllocationController::class, 'create'])->name('allocations.create');
Route::post('allocations', [AllocationController::class, 'store'])->name('allocations.store');
Route::get('allocations/{allocation}', [AllocationController::class, 'show'])->name('allocations.show');
Route::put('allocations/{allocation}', [AllocationController::class, 'update'])->name('allocations.update');
Route::delete('allocations/{allocation}', [AllocationController::class, 'destroy'])->name('allocations.destroy');
Route::post('allocations/{allocation}/receipts', [AllocationController::class, 'attach'])->name('allocations.attach');
Route::delete('allocations/{allocation}/receipts/{receipt}', [AllocationController::class, 'detach'])->name('allocations.detach');
Route::get('allocations/{allocation}/pdf', [AllocationController::class, 'pdf'])->name('allocations.pdf');

// Receipt detail + image (wildcard — must stay last).
Route::get('{receipt}', [ReceiptController::class, 'show'])->name('show');
Route::put('{receipt}', [ReceiptController::class, 'update'])->name('update');
Route::delete('{receipt}', [ReceiptController::class, 'destroy'])->name('destroy');
Route::get('{receipt}/image/{variant?}', [ReceiptImageController::class, 'show'])->name('image');
