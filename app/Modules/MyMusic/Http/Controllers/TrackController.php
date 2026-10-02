<?php

namespace App\Modules\MyMusic\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MyMusic\Models\Play;
use App\Modules\MyMusic\Models\Track;
use App\Modules\MyMusic\Models\Video;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    /** Manual correction — marks the track `manual` so enrichment never overwrites it. */
    public function update(Request $request, Track $track)
    {
        $data = $request->validate([
            'artist' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:500'],
            'album' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'genres' => ['nullable', 'string', 'max:500'], // comma-separated
        ]);

        $genres = collect(explode(',', $data['genres'] ?? ''))
            ->map(fn ($g) => trim($g))
            ->filter()
            ->values()
            ->all();

        $track->update([
            'artist' => $data['artist'] ?? null,
            'title' => $data['title'],
            'album' => $data['album'] ?? null,
            'year' => $data['year'] ?? null,
            'genres' => $genres ?: null,
            'enrich_status' => 'manual',
            'enriched_at' => now(),
        ]);

        return response()->json(['track' => $track->fresh()]);
    }

    /** Flip a video between music / not-music; parks or revives its track. */
    public function toggleMusic(Video $video)
    {
        $video->update(['is_music' => ! $video->is_music]);

        $track = $video->track;
        if ($track && ! $video->is_music && $track->enrich_status === 'pending') {
            $track->update(['enrich_status' => 'skipped']);
        } elseif ($track && $video->is_music && $track->enrich_status === 'skipped') {
            $track->update(['enrich_status' => 'pending']);
        }

        return response()->json(['is_music' => $video->is_music]);
    }

    /** Player reported embed error 101/150 — remember and skip next time. */
    public function markNotEmbeddable(Video $video)
    {
        $video->update(['embeddable' => false]);

        return response()->json(['embeddable' => false]);
    }

    /** Record a counted listen (fired once the player crosses the real-listen threshold). */
    public function recordPlay(Video $video)
    {
        $video->increment('play_count');
        $video->update(['last_played_at' => now()]);
        Play::create(['video_id' => $video->video_id, 'played_at' => now()]);

        return response()->json(['play_count' => $video->play_count]);
    }

    /** Set / clear the manual slow/fast tempo mark (stored on the video, like the rating). */
    public function tempo(Request $request, Video $video)
    {
        $data = $request->validate(['tempo' => ['nullable', 'in:slow,fast']]);
        $video->update(['tempo' => $data['tempo'] ?? null]);

        return response()->json(['tempo' => $video->tempo]);
    }

    /** Set / clear the 0–3 star rating (rating stored on the video, the stable per-row entity). */
    public function rate(Request $request, Video $video)
    {
        $data = $request->validate(['rating' => ['nullable', 'integer', 'between:0,3']]);
        $rating = $data['rating'] ?? null;
        $video->update(['rating' => $rating === 0 ? null : $rating]);

        return response()->json(['rating' => $video->rating]);
    }
}
