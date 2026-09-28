<?php

namespace App\Modules\MyMusic\Support;

use App\Modules\MyMusic\Models\Video;

/**
 * Pulls the Liked-videos playlist ("LL") into music_videos. Insert-only:
 * new video_ids are added, existing rows only get their liked_position and
 * thumbnail refreshed, nothing is ever deleted. Shared by the browser-driven
 * chunked sync (SyncController) and the daily music:sync cron command.
 */
class LikedSyncer
{
    public function __construct(protected YouTubeClient $yt)
    {
    }

    /**
     * @param  ?int  $maxPages  Pages of 50 to fetch this call; null = to the end.
     * @return array{inserted:int,refreshed:int,scanned:int,nextPageToken:?string}
     */
    public function sync(?string $pageToken = null, ?int $maxPages = null): array
    {
        $inserted = 0;
        $refreshed = 0;
        $scanned = 0;

        do {
            $response = $this->yt->get('playlistItems', array_filter([
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
        } while ($pageToken && ($maxPages === null || --$maxPages > 0));

        return [
            'inserted' => $inserted,
            'refreshed' => $refreshed,
            'scanned' => $scanned,
            'nextPageToken' => $pageToken,
        ];
    }

    protected static function utf8(?string $value): ?string
    {
        if ($value === null || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
