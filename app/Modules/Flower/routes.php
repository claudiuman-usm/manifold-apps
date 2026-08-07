<?php

use App\Modules\Flower\Http\Controllers\RunController;
use App\Modules\Flower\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

/*
 * Flower routes. The ModuleServiceProvider loads this file inside a group that
 * applies the "flower" URL prefix, the "flower." route-name prefix, and the
 * web + auth middleware — so names below are relative (e.g. "index" -> "flower.index").
 */

Route::get('/', [TemplateController::class, 'index'])->name('index');

// Template management.
Route::get('templates/create', [TemplateController::class, 'create'])->name('templates.create');
Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

// Run history (per template).
Route::get('templates/{template}/history', [RunController::class, 'history'])->name('templates.history');

// Runs.
Route::post('templates/{template}/runs', [RunController::class, 'start'])->name('runs.start');
Route::get('runs/{run}', [RunController::class, 'show'])->name('runs.show');
Route::post('runs/{run}/advance', [RunController::class, 'advance'])->name('runs.advance');
Route::delete('runs/{run}', [RunController::class, 'destroy'])->name('runs.destroy');
