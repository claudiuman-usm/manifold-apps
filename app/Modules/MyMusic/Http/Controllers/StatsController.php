<?php

namespace App\Modules\MyMusic\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MyMusic\Models\Play;
use App\Modules\MyMusic\Models\Video;
use App\Modules\MyMusic\Support\GenreFamilies;
use App\Modules\MyMusic\Support\TasteAnalyzer;

/**
 * Listening statistics derived entirely from local data (no external cost):
 * play counts, ratings, genre families, decades — with a rediscovery slant
 * (top-rated but long-unplayed, never-played).
 */
class StatsController extends Controller
{
    public function index()
    {
        $music = Video::query()->with('track')->where('is_music', true)->get();

        return view('music::stats', $this->stats($music) + [
            'aiConfigured' => (bool) config('music.ai.api_key'),
        ]);
    }

    /** @return array<string,mixed> */
    protected function stats($music): array
    {
        $totalPlays = (int) $music->sum('play_count');

        // Top artists by total plays, then by count of tracks (ties).
        $byArtist = $music->groupBy(fn ($v) => $v->track?->artist ?: ($v->channel_title ?: '—'))
            ->map(fn ($rows, $name) => [
                'name' => $name,
                'plays' => (int) $rows->sum('play_count'),
                'tracks' => $rows->count(),
            ])
            ->sortByDesc(fn ($r) => [$r['plays'], $r['tracks']])
            ->take(10)->values();

        // Genre families across the library, and by plays.
        $famTracks = [];
        $famPlays = [];
        foreach ($music as $v) {
            foreach (GenreFamilies::familiesFor($v->track?->genres ?? []) as $f) {
                $famTracks[$f] = ($famTracks[$f] ?? 0) + 1;
                $famPlays[$f] = ($famPlays[$f] ?? 0) + $v->play_count;
            }
        }
        arsort($famPlays);
        $genres = collect($famPlays)->map(fn ($plays, $name) => [
            'name' => $name, 'plays' => $plays, 'tracks' => $famTracks[$name] ?? 0,
        ])->values();

        // Decade distribution (by track year).
        $decades = $music->filter(fn ($v) => $v->track?->year)
            ->groupBy(fn ($v) => intdiv($v->track->year, 10) * 10)
            ->map->count()->sortKeys();

        $mapRow = fn (Video $v) => [
            'video_id' => $v->video_id,
            'artist' => $v->track?->artist ?: $v->channel_title,
            'title' => $v->track?->title ?: $v->raw_title,
            'plays' => $v->play_count,
            'rating' => $v->rating,
            'last' => optional($v->last_played_at)->diffForHumans(),
        ];

        $mostPlayed = $music->sortByDesc('play_count')->take(10)->map($mapRow)->values();

        // Rediscovery: highly rated but not played in a long while (or never).
        $lostFavorites = $music->filter(fn ($v) => ($v->rating ?? 0) >= 2)
            ->sortBy(fn ($v) => $v->last_played_at?->timestamp ?? 0)
            ->take(10)->map($mapRow)->values();

        $topRated = $music->filter(fn ($v) => ($v->rating ?? 0) > 0)
            ->sortByDesc(fn ($v) => [$v->rating, $v->play_count])
            ->take(10)->map($mapRow)->values();

        return [
            'totals' => [
                'tracks' => $music->count(),
                'plays' => $totalPlays,
                'rated' => $music->filter(fn ($v) => ($v->rating ?? 0) > 0)->count(),
                'played' => $music->filter(fn ($v) => $v->play_count > 0)->count(),
                'plays_this_month' => Play::where('played_at', '>=', now()->startOfMonth())->count(),
            ],
            'topArtists' => $byArtist,
            'genres' => $genres,
            'decades' => $decades,
            'mostPlayed' => $mostPlayed,
            'lostFavorites' => $lostFavorites,
            'topRated' => $topRated,
        ];
    }

    /** On-demand AI taste analysis (reuses the Anthropic SDK + ANTHROPIC_API_KEY). */
    public function analyze(TasteAnalyzer $analyzer)
    {
        $music = Video::query()->with('track')->where('is_music', true)->get();
        $s = $this->stats($music);

        $summary = $analyzer->analyze([
            'total_plays' => $s['totals']['plays'],
            'top_artists' => $s['topArtists']->take(8)->all(),
            'genres' => $s['genres']->take(8)->all(),
            'most_played' => $s['mostPlayed']->take(10)->all(),
            'top_rated' => $s['topRated']->take(10)->all(),
            'lost_favorites' => $s['lostFavorites']->take(8)->all(),
        ]);

        if ($summary === null) {
            return response()->json(['error' => __('music::messages.stats.ai_unavailable')], 422);
        }

        return response()->json(['analysis' => $summary]);
    }
}
