<?php

use App\Modules\MyMusic\Http\Controllers\EnrichController;
use App\Modules\MyMusic\Http\Controllers\MusicController;
use App\Modules\MyMusic\Http\Controllers\OAuthController;
use App\Modules\MyMusic\Http\Controllers\SyncController;
use App\Modules\MyMusic\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

/*
 * My Music routes — loaded by ModuleServiceProvider with the "music" URL
 * prefix, "music." name prefix, and web + auth middleware.
 */

Route::get('/', [MusicController::class, 'index'])->name('index');
Route::get('data', [MusicController::class, 'data'])->name('data');

// Listening statistics + AI taste analysis.
Route::get('stats', [\App\Modules\MyMusic\Http\Controllers\StatsController::class, 'index'])->name('stats');
Route::post('stats/analyze', [\App\Modules\MyMusic\Http\Controllers\StatsController::class, 'analyze'])->name('stats.analyze');

// Google OAuth (web flow).
Route::get('oauth/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('oauth/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
Route::delete('oauth', [OAuthController::class, 'disconnect'])->name('oauth.disconnect');

// Chunked Liked-videos sync (JSON, looped by the frontend).
Route::post('sync', [SyncController::class, 'run'])->name('sync');

// Chunked MusicBrainz enrichment (JSON, looped by the frontend; cron does the same).
Route::post('enrich', [EnrichController::class, 'run'])->name('enrich');
Route::get('enrich/progress', [EnrichController::class, 'progress'])->name('enrich.progress');

// Playlists — created on YouTube from library filters, chunked + resumable.
Route::get('playlists', [\App\Modules\MyMusic\Http\Controllers\PlaylistController::class, 'index'])->name('playlists.index');
Route::post('playlists', [\App\Modules\MyMusic\Http\Controllers\PlaylistController::class, 'store'])->name('playlists.store');
Route::post('playlists/{playlist}/process', [\App\Modules\MyMusic\Http\Controllers\PlaylistController::class, 'process'])->name('playlists.process');
Route::post('playlists/{playlist}/sync', [\App\Modules\MyMusic\Http\Controllers\PlaylistController::class, 'sync'])->name('playlists.sync');
Route::delete('playlists/{playlist}', [\App\Modules\MyMusic\Http\Controllers\PlaylistController::class, 'destroy'])->name('playlists.destroy');

// Track corrections + video flags.
Route::put('tracks/{track}', [TrackController::class, 'update'])->name('tracks.update');
Route::post('videos/{video}/toggle-music', [TrackController::class, 'toggleMusic'])->name('videos.toggle-music');
Route::post('videos/{video}/not-embeddable', [TrackController::class, 'markNotEmbeddable'])->name('videos.not-embeddable');
Route::post('videos/{video}/played', [TrackController::class, 'recordPlay'])->name('videos.played');
Route::post('videos/{video}/rate', [TrackController::class, 'rate'])->name('videos.rate');
