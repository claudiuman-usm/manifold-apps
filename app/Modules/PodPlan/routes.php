<?php

use App\Modules\PodPlan\Http\Controllers\ChecklistController;
use App\Modules\PodPlan\Http\Controllers\InventoryController;
use App\Modules\PodPlan\Http\Controllers\PlanController;
use App\Modules\PodPlan\Http\Controllers\ProjectController;
use App\Modules\PodPlan\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Route;

/*
 * PodPlan routes — loaded by ModuleServiceProvider with the "podplan" URL
 * prefix, "podplan." name prefix, and web + auth middleware.
 */

// Projects.
Route::get('/', [ProjectController::class, 'index'])->name('index');
Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

// Plan (the top-down diagram). Every change answers with the full plan state.
Route::get('projects/{project}/plan', [PlanController::class, 'show'])->name('plan');
Route::get('projects/{project}/state', [PlanController::class, 'state'])->name('plan.state');
Route::post('projects/{project}/placements', [PlanController::class, 'add'])->name('plan.add');
Route::patch('projects/{project}/placements/{placement}', [PlanController::class, 'move'])->name('plan.move');
Route::delete('projects/{project}/placements/{placement}', [PlanController::class, 'remove'])->name('plan.remove');
Route::patch('projects/{project}/live', [PlanController::class, 'live'])->name('plan.live');
Route::patch('projects/{project}/axis', [PlanController::class, 'axis'])->name('plan.axis');
Route::post('projects/{project}/answers', [PlanController::class, 'answer'])->name('plan.answer');
Route::post('projects/{project}/describe', [PlanController::class, 'describe'])->name('plan.describe');
Route::post('projects/{project}/describe/keep', [PlanController::class, 'keep'])->name('plan.keep');
Route::post('projects/{project}/describe/undo', [PlanController::class, 'undo'])->name('plan.undo');

// Checklist (the only place items get ticked).
Route::get('projects/{project}/checklist', [ChecklistController::class, 'show'])->name('checklist');
Route::get('projects/{project}/checklist/state', [ChecklistController::class, 'state'])->name('checklist.state');
Route::post('projects/{project}/ticks', [ChecklistController::class, 'tick'])->name('checklist.tick');
Route::post('projects/{project}/manual-items', [ChecklistController::class, 'addManual'])->name('checklist.manual.add');
Route::delete('projects/{project}/manual-items/{item}', [ChecklistController::class, 'removeManual'])->name('checklist.manual.remove');
Route::post('projects/{project}/reset', [ChecklistController::class, 'reset'])->name('checklist.reset');
Route::post('projects/{project}/share', [ChecklistController::class, 'share'])->name('checklist.share');
Route::delete('projects/{project}/share', [ChecklistController::class, 'unshare'])->name('checklist.unshare');

// Inventory and rules.
Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::post('inventory/items', [InventoryController::class, 'store'])->name('inventory.store');
Route::put('inventory/items/{item}', [InventoryController::class, 'update'])->name('inventory.update');
Route::delete('inventory/items/{item}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
Route::post('inventory/items/{item}/reset-rules', [InventoryController::class, 'resetRules'])->name('inventory.reset');
Route::post('inventory/items/{item}/apply-to-type', [InventoryController::class, 'applyToType'])->name('inventory.apply');
Route::put('inventory/types/{type}', [InventoryController::class, 'updateType'])->name('inventory.types.update');
Route::post('inventory/general-rules', [InventoryController::class, 'storeGeneral'])->name('inventory.general.store');
Route::delete('inventory/general-rules/{rule}', [InventoryController::class, 'destroyGeneral'])->name('inventory.general.destroy');

/*
 * Shared packing session: one project's checklist behind a secret link, for
 * colleagues who have no login. These three routes drop the auth middleware;
 * the token in the URL is the only credential and reaches nothing else.
 */
Route::middleware('throttle:240,1')->withoutMiddleware('auth')->group(function () {
    Route::get('s/{token}', [ShareController::class, 'show'])->name('share.show');
    Route::get('s/{token}/state', [ShareController::class, 'state'])->name('share.state');
    Route::get('s/{token}/plan', [ShareController::class, 'plan'])->name('share.plan');
    Route::post('s/{token}/ticks', [ShareController::class, 'tick'])->name('share.tick');
});
