<?php

namespace App\Modules\MyMusic\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MyMusic\Models\Playlist;
use App\Modules\MyMusic\Models\PlaylistItem;
use App\Modules\MyMusic\Models\QuotaEntry;
use App\Modules\MyMusic\Support\YouTubeApiException;
use App\Modules\MyMusic\Support\YouTubeClient;
use Illuminate\Http\Request;

/**
 * Real YouTube playlists built from library filters.
 *
 * Writes are expensive (playlists.insert = 50 units, each playlistItems.insert
 * = 50 units, 10,000/day budget ≈ 199 tracks/day), so creation runs in small
 * chunks the frontend loops over; quotaExceeded parks the playlist with its
 * remaining queue and Resume simply calls process again another day.
 */
class PlaylistController extends Controller
{
    /** Items inserted per process request (3 × 50 = 150 units, a few seconds). */
    protected const ITEMS_PER_CHUNK = 3;

    public function index()
    {
        return view('music::playlists', [
            'playlists' => Playlist::withCount('items')->latest()->get(),
            'quotaToday' => QuotaEntry::todayUnits(),
            'quotaBudget' => (int) config('music.daily_quota', 10000),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'filter' => ['nullable', 'array'],
            'videoIds' => ['required', 'array', 'min:1'],
            'videoIds.*' => ['string', 'max:20'],
        ]);

        $playlist = Playlist::create([
            'name' => $data['name'],
            'filter_json' => $data['filter'] ?? null,
            'queue' => array_values(array_unique($data['videoIds'])),
            'status' => 'pending',
        ]);

        return response()->json(['playlist' => $this->payload($playlist)]);
    }

    /** One chunk of work: create the YT playlist if needed, then insert a few items. */
    public function process(Playlist $playlist, YouTubeClient $yt)
    {
        $queue = $playlist->queue ?? [];

        try {
            if (! $playlist->youtube_id && $queue !== []) {
                $created = $yt->post('playlists', ['part' => 'snippet,status'], [
                    'snippet' => ['title' => $playlist->name],
                    'status' => ['privacyStatus' => 'private'],
                ], 50, 'playlists.insert');

                $playlist->update(['youtube_id' => $created['id'] ?? null, 'status' => 'creating']);
            }

            for ($i = 0; $i < self::ITEMS_PER_CHUNK && $queue !== []; $i++) {
                $videoId = $queue[0];

                try {
                    $yt->post('playlistItems', ['part' => 'snippet'], [
                        'snippet' => [
                            'playlistId' => $playlist->youtube_id,
                            'resourceId' => ['kind' => 'youtube#video', 'videoId' => $videoId],
                        ],
                    ], 50, 'playlistItems.insert');

                    PlaylistItem::create([
                        'playlist_id' => $playlist->id,
                        'video_id' => $videoId,
                        'position' => $playlist->items()->count(),
                    ]);
                } catch (YouTubeApiException $e) {
                    if ($e->quotaExceeded) {
                        throw $e;
                    }
                    // Deleted/region-blocked video — drop it and move on.
                    $playlist->increment('failed_count');
                }

                array_shift($queue);
                $playlist->update(['queue' => $queue]);
            }
        } catch (YouTubeApiException $e) {
            if ($e->quotaExceeded) {
                $playlist->update(['status' => 'quota_paused', 'queue' => $queue]);

                return response()->json([
                    'error' => $e->getMessage(),
                    'quotaExceeded' => true,
                    'playlist' => $this->payload($playlist->fresh()),
                ], 422);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        if ($queue === []) {
            $playlist->update(['status' => 'ready', 'last_synced_at' => now()]);
        } elseif ($playlist->status === 'quota_paused') {
            $playlist->update(['status' => 'creating']);
        }

        return response()->json(['playlist' => $this->payload($playlist->fresh())]);
    }

    /** Append filter-matched videos that aren't in the playlist yet, then Resume/process. */
    public function sync(Request $request, Playlist $playlist)
    {
        $data = $request->validate([
            'videoIds' => ['required', 'array'],
            'videoIds.*' => ['string', 'max:20'],
        ]);

        $existing = $playlist->items()->pluck('video_id')->all();
        $queue = $playlist->queue ?? [];
        $new = array_values(array_diff(array_unique($data['videoIds']), $existing, $queue));

        $playlist->update([
            'queue' => array_merge($queue, $new),
            'status' => $new === [] && $queue === [] ? $playlist->status : 'creating',
        ]);

        return response()->json(['added' => count($new), 'playlist' => $this->payload($playlist->fresh())]);
    }

    public function destroy(Playlist $playlist)
    {
        // Local bookkeeping only — the YouTube playlist itself is left alone.
        $playlist->items()->delete();
        $playlist->delete();

        return response()->json(['ok' => true]);
    }

    protected function payload(Playlist $playlist): array
    {
        return [
            'id' => $playlist->id,
            'youtube_id' => $playlist->youtube_id,
            'name' => $playlist->name,
            'status' => $playlist->status,
            'inserted' => $playlist->items()->count(),
            'queued' => count($playlist->queue ?? []),
            'failed' => $playlist->failed_count,
            'quotaToday' => QuotaEntry::todayUnits(),
            'quotaRemaining' => QuotaEntry::remainingToday(),
        ];
    }
}
