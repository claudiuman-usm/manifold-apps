<?php

namespace App\Modules\MyMusic\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MyMusic\Models\QuotaEntry;
use App\Modules\MyMusic\Models\Video;
use App\Modules\MyMusic\Support\LikedSyncer;
use App\Modules\MyMusic\Support\YouTubeApiException;
use Illuminate\Http\Request;

/**
 * Browser-driven chunked sync: each request fetches a few pages of 50 and
 * returns the next page token; the frontend loops until done. The actual
 * work lives in LikedSyncer (shared with the daily music:sync cron).
 */
class SyncController extends Controller
{
    public function run(Request $request, LikedSyncer $syncer)
    {
        try {
            $stats = $syncer->sync(
                $request->input('pageToken') ?: null,
                (int) config('music.sync_pages_per_request', 5),
            );
        } catch (YouTubeApiException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'quotaExceeded' => $e->quotaExceeded,
            ], 422);
        }

        return response()->json($stats + [
            'done' => $stats['nextPageToken'] === null,
            'total' => Video::count(),
            'quotaToday' => QuotaEntry::todayUnits(),
        ]);
    }
}
