<?php

namespace App\Modules\MyMusic\Support;

use App\Modules\MyMusic\Models\Track;
use App\Modules\MyMusic\Models\Video;

/**
 * Pulls the Liked-videos playlist ("LL") into music_videos. New video_ids are
 * added, existing rows get their liked_position and thumbnail refreshed,
 * nothing is ever deleted. Shared by the browser-driven chunked sync
 * (SyncController) and the daily music:sync cron command.
 *
 * Unliking on YouTube is the user's way of saying "not music": a full pass
 * (cron) sweeps rows no longer present in the playlist — is_music=false,
 * unliked_at stamped. Re-liking flips only those rows back to music;
 * parser/manual non-music (unliked_at null) is never overridden.
 */
class LikedSyncer
{
    public function __construct(protected YouTubeClient $yt)
    {
    }

    /**
     * @param  ?int  $maxPages  Pages of 50 to fetch this call; null = to the end.
     * @return array{inserted:int,refreshed:int,scanned:int,reliked:int,unliked:int,nextPageToken:?string}
     */
    public function sync(?string $pageToken = null, ?int $maxPages = null): array
    {
        // Only a complete walk of the playlist can prove absence — the
        // chunked browser sync (pageToken/maxPages set) never sweeps.
        $fullPass = $pageToken === null && $maxPages === null;

        $inserted = 0;
        $refreshed = 0;
        $scanned = 0;
        $reliked = 0;
        $seen = [];

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

                $seen[$videoId] = true;

                $attrs = [
                    'liked_position' => $snippet['position'] ?? null,
                    'thumbnail' => $snippet['thumbnails']['medium']['url']
                        ?? $snippet['thumbnails']['default']['url'] ?? null,
                ];

                $existing = Video::withTrashed()->where('video_id', $videoId)->first();

                if ($existing) {
                    if ($existing->unliked_at !== null) {
                        // Back in the playlist after an unlike sweep → re-liked.
                        $attrs += [
                            'is_music' => true,
                            'unliked_at' => null,
                            'liked_at' => $snippet['publishedAt'] ?? $existing->liked_at,
                        ];
                        $this->reviveTrack($existing);
                        $reliked++;
                    }

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

        // Scanned-empty guard: an API hiccup returning an empty playlist must
        // not sweep the whole library.
        $unliked = ($fullPass && $scanned > 0) ? $this->sweepUnliked($seen) : 0;

        return [
            'inserted' => $inserted,
            'refreshed' => $refreshed,
            'scanned' => $scanned,
            'reliked' => $reliked,
            'unliked' => $unliked,
            'nextPageToken' => $pageToken,
        ];
    }

    /** Rows absent from the full playlist walk were unliked → non-music. */
    protected function sweepUnliked(array $seen): int
    {
        $missing = Video::query()
            ->whereNull('unliked_at')
            ->pluck('video_id')
            ->reject(fn ($id) => isset($seen[$id]));

        foreach ($missing->chunk(500) as $chunk) {
            Video::query()->whereIn('video_id', $chunk)
                ->update(['is_music' => false, 'unliked_at' => now()]);
            // Park their queued tracks, same as the manual not-music toggle.
            Track::query()->whereIn('video_id', $chunk)
                ->where('enrich_status', 'pending')
                ->update(['enrich_status' => 'skipped']);
        }

        return $missing->count();
    }

    protected function reviveTrack(Video $video): void
    {
        $track = $video->track;
        if ($track && $track->enrich_status === 'skipped') {
            $track->update(['enrich_status' => 'pending']);
        }
    }

    protected static function utf8(?string $value): ?string
    {
        if ($value === null || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
