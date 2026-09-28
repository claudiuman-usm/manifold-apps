<?php

namespace App\Modules\MyMusic\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MyMusic\Models\QuotaEntry;
use App\Modules\MyMusic\Models\Video;
use App\Modules\MyMusic\Support\YouTubeApiException;
use App\Modules\MyMusic\Support\YouTubeClient;
use Illuminate\Http\Request;

/**
 * Chunked sync of the Liked-videos playlist ("LL"). Each request fetches a few
 * pages of 50 and returns the next page token; the frontend loops until done.
 * Insert-only for new video_ids — existing rows only get their liked_position
 * and thumbnail refreshed, nothing is ever deleted.
 */
class SyncController extends Controller
{
    public function run(Request $request, YouTubeClient $yt)
    {
        $pageToken = $request->input('pageToken') ?: null;
        $pagesLeft = (int) config('music.sync_pages_per_request', 5);
        $inserted = 0;
        $refreshed = 0;
        $scanned = 0;

        try {
            do {
                $response = $yt->get('playlistItems', array_filter([
                    'part' => 'snippet,contentDetails',
                    'playlistId' => config('music.liked_playlist', 'LL'),
                    'maxResults' => 50,
                    'pageToken' => $pageToken,
                ]), 1, 'playlistItems.list');

                foreach ($response['items'] ?? [] as $item) {
                    $scanned++;
                    $snippet = $item['snippet'] ?? [];
                    $videoId = $item['contentDetails']['videoId'] ?? ($snippet['resourceId']['videoId'] ?? null);

                    if (! $videoId) {
                        continue;
                    }

                    $attrs = [
                        'liked_position' => $snippet['position'] ?? null,
                        'thumbnail' => $snippet['thumbnails']['medium']['url']
                            ?? $snippet['thumbnails']['default']['url'] ?? null,
                    ];

                    $existing = Video::withTrashed()->where('video_id', $videoId)->first();

                    if ($existing) {
                        $existing->fill($attrs)->save();
                        $refreshed++;
                        continue;
                    }

                    Video::create($attrs + [
                        'video_id' => $videoId,
                        // Titles occasionally carry invalid UTF-8 — strip it here
                        // or every JSON response containing the row breaks.
                        'raw_title' => self::utf8($snippet['title'] ?? ''),
                        // videoOwnerChannelTitle is the uploader; channelTitle would be us.
                        'channel_title' => self::utf8($snippet['videoOwnerChannelTitle'] ?? null),
                        'video_published_at' => $item['contentDetails']['videoPublishedAt'] ?? null,
                        'liked_at' => $snippet['publishedAt'] ?? null,
                        'fetched_at' => now(),
                    ]);
                    $inserted++;
                }

                $pageToken = $response['nextPageToken'] ?? null;
                $pagesLeft--;
            } while ($pageToken && $pagesLeft > 0);
        } catch (YouTubeApiException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'quotaExceeded' => $e->quotaExceeded,
            ], 422);
        }

        return response()->json([
            'inserted' => $inserted,
            'refreshed' => $refreshed,
            'scanned' => $scanned,
            'nextPageToken' => $pageToken,
            'done' => $pageToken === null,
            'total' => Video::count(),
            'quotaToday' => QuotaEntry::todayUnits(),
        ]);
    }

    protected static function utf8(?string $value): ?string
    {
        if ($value === null || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
