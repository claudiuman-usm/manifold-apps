<?php

namespace App\Modules\MyMusic\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MyMusic\Models\GoogleToken;
use App\Modules\MyMusic\Models\QuotaEntry;
use App\Modules\MyMusic\Models\Video;
use App\Modules\MyMusic\Support\EnrichmentRunner;
use App\Modules\MyMusic\Support\YouTubeClient;

class MusicController extends Controller
{
    public function index(YouTubeClient $yt, EnrichmentRunner $runner)
    {
        return view('music::index', [
            'configured' => $yt->isConfigured(),
            'token' => GoogleToken::current(),
            'videoCount' => Video::count(),
            'lastFetchedAt' => Video::max('fetched_at'),
            'quotaToday' => QuotaEntry::todayUnits(),
            'quotaBudget' => (int) config('music.daily_quota', 10000),
            'tracks' => $this->payload(),
            'enrichProgress' => $runner->progress(),
        ]);
    }

    /** Fresh library payload — the page refetches this after sync/enrich runs. */
    public function data(EnrichmentRunner $runner)
    {
        // JSON_INVALID_UTF8_SUBSTITUTE: a stray badly-encoded title must not
        // take down the whole payload (sync sanitizes new rows, this guards
        // anything already stored).
        return response()->json([
            'tracks' => $this->payload(),
            'progress' => $runner->progress(),
            'quotaToday' => QuotaEntry::todayUnits(),
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    protected function payload()
    {
        // Whole library as one JSON payload — filtering/sorting/queueing all
        // happen client-side (a few thousand rows is nothing for the browser,
        // and the player needs the filtered list in JS anyway).
        return Video::with('track')
            ->orderByRaw('liked_position IS NULL, liked_position')
            ->get()
            ->map(fn (Video $v) => [
                'vid' => $v->id,
                'v' => $v->video_id,
                't' => $v->track?->id,
                'a' => $v->track?->artist,
                'ti' => $v->track?->title ?? $v->raw_title,
                'al' => $v->track?->album,
                'y' => $v->track?->year,
                'g' => $v->track?->genres ?? [],
                'gf' => \App\Modules\MyMusic\Support\GenreFamilies::familiesFor($v->track?->genres ?? []),
                'c' => $v->channel_title,
                's' => $v->track?->enrich_status ?? 'pending',
                'm' => $v->is_music,
                'e' => $v->embeddable,
                'p' => $v->liked_position,
                'th' => $v->thumbnail,
                'rt' => $v->rating,
                'pc' => $v->play_count,
            ])
            ->values();
    }
}
