<?php

namespace App\Modules\MyMusic\Support;

use App\Modules\MyMusic\Models\Track;
use App\Modules\MyMusic\Models\Video;

/**
 * Drives parse + MusicBrainz enrichment in resumable chunks. Called both by
 * the music:enrich artisan command (cron in prod) and the chunked HTTP
 * endpoint the UI loops over — each chunk is small enough for shared hosting.
 */
class EnrichmentRunner
{
    public function __construct(protected MusicBrainzClient $musicBrainz)
    {
    }

    /**
     * Create track rows for synced videos that don't have one yet (cheap, no
     * network). The is_music heuristic lands on the video row exactly once,
     * here — later manual toggles are never overwritten.
     */
    public function parseMissing(): int
    {
        $created = 0;

        Video::query()
            ->whereNotIn('video_id', Track::query()->withTrashed()->select('video_id'))
            ->orderBy('id')
            ->chunkById(200, function ($videos) use (&$created) {
                foreach ($videos as $video) {
                    $parsed = TitleParser::parse($video->raw_title, $video->channel_title);

                    if (! $parsed['is_music'] && $video->is_music) {
                        $video->update(['is_music' => false]);
                    }

                    Track::create([
                        'video_id' => $video->video_id,
                        'artist' => $parsed['artist'],
                        'title' => $parsed['title'],
                        'enrich_status' => $parsed['is_music'] ? 'pending' : 'skipped',
                    ]);
                    $created++;
                }
            });

        return $created;
    }

    /**
     * Enrich pending tracks until ~$maxLookups MusicBrainz requests are spent
     * (a track can cost 2: recording search + artist-tags fallback).
     *
     * @return array{processed:int,ok:int,not_found:int,lookups:int}
     */
    public function enrich(int $maxLookups): array
    {
        $stats = ['processed' => 0, 'ok' => 0, 'not_found' => 0, 'lookups' => 0];

        // Videos flipped to not-music since parsing: park their pending tracks.
        Track::query()
            ->where('enrich_status', 'pending')
            ->whereIn('video_id', Video::query()->where('is_music', false)->select('video_id'))
            ->update(['enrich_status' => 'skipped']);

        while ($stats['lookups'] < $maxLookups) {
            $track = Track::query()
                ->where('enrich_status', 'pending')
                ->orderBy('id')
                ->first();

            if (! $track) {
                break;
            }

            $artist = $track->artist ?: TitleParser::artistFromChannel($track->video?->channel_title);

            if (! $artist) {
                $track->update(['enrich_status' => 'not_found', 'enriched_at' => now()]);
                $stats['processed']++;
                $stats['not_found']++;
                continue;
            }

            $before = $this->musicBrainz->requestCount;
            $match = $this->musicBrainz->searchRecording($artist, $track->title);
            $stats['lookups'] += max(1, $this->musicBrainz->requestCount - $before);
            $stats['processed']++;

            if ($match) {
                $confident = $match['score'] >= 90;
                $track->update([
                    'artist' => $confident ? $match['artist'] : $track->artist,
                    'title' => $confident ? $match['title'] : $track->title,
                    'album' => $match['album'],
                    'year' => $match['year'],
                    'genres' => $match['genres'] ?: null,
                    'enrich_status' => 'ok',
                    'enriched_at' => now(),
                ]);
                $stats['ok']++;
            } else {
                $track->update(['enrich_status' => 'not_found', 'enriched_at' => now()]);
                $stats['not_found']++;
            }
        }

        return $stats;
    }

    /** @return array{videos:int,parsed:int,pending:int,ok:int,not_found:int,manual:int,skipped:int} */
    public function progress(): array
    {
        $byStatus = Track::query()
            ->selectRaw('enrich_status, count(*) as n')
            ->groupBy('enrich_status')
            ->pluck('n', 'enrich_status');

        return [
            'videos' => Video::count(),
            'parsed' => Track::count(),
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'ok' => (int) ($byStatus['ok'] ?? 0),
            'not_found' => (int) ($byStatus['not_found'] ?? 0),
            'manual' => (int) ($byStatus['manual'] ?? 0),
            'skipped' => (int) ($byStatus['skipped'] ?? 0),
        ];
    }
}
