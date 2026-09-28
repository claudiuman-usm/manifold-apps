<?php

namespace App\Modules\MyMusic\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal MusicBrainz search client. Keyless, but etiquette-bound:
 * a descriptive User-Agent and max 1 request/second — the limiter lives
 * here so every caller is throttled no matter the path.
 */
class MusicBrainzClient
{
    protected const BASE = 'https://musicbrainz.org/ws/2/';
    protected const MIN_GAP_US = 1_100_000; // 1.1s between requests

    protected static float $lastRequestAt = 0.0;

    /** HTTP requests actually made — callers use this to budget a chunk. */
    public int $requestCount = 0;

    /** In-process cache of artist → tag names (many tracks share an artist). */
    protected array $artistTags = [];

    /**
     * Best recording match, or null.
     *
     * @return array{artist:string,title:string,album:?string,year:?int,genres:array<string>,score:int}|null
     */
    public function searchRecording(string $artist, string $title): ?array
    {
        $query = sprintf('recording:"%s" AND artist:"%s"', $this->escape($title), $this->escape($artist));
        $data = $this->request('recording', ['query' => $query, 'limit' => 5]);

        $best = null;
        foreach ($data['recordings'] ?? [] as $rec) {
            if (($rec['score'] ?? 0) < 70) {
                continue;
            }
            // Prefer the highest score; among equals, one that has releases.
            if (! $best
                || ($rec['score'] <=> $best['score']) > 0
                || ($rec['score'] === $best['score'] && ! empty($rec['releases']) && empty($best['releases']))) {
                $best = $rec;
            }
        }

        if (! $best) {
            return null;
        }

        $credits = array_map(fn ($c) => trim(($c['name'] ?? '').($c['joinphrase'] ?? '')), $best['artist-credit'] ?? []);
        $mbArtist = trim(implode('', $credits)) ?: $artist;

        $year = null;
        if (! empty($best['first-release-date'])) {
            $year = (int) substr($best['first-release-date'], 0, 4) ?: null;
        }

        $album = null;
        foreach ($best['releases'] ?? [] as $release) {
            $album ??= $release['title'] ?? null;
            if (! $year && ! empty($release['date'])) {
                $year = (int) substr($release['date'], 0, 4) ?: null;
            }
        }

        $genres = $this->tagNames($best['tags'] ?? []);
        if (! $genres) {
            $genres = $this->artistTags($mbArtist);
        }

        return [
            'artist' => $mbArtist,
            'title' => $best['title'] ?? $title,
            'album' => $album,
            'year' => $year,
            'genres' => $genres,
            'score' => (int) $best['score'],
        ];
    }

    /** @return array<string> */
    public function artistTags(string $artist): array
    {
        $key = mb_strtolower($artist);

        if (array_key_exists($key, $this->artistTags)) {
            return $this->artistTags[$key];
        }

        $data = $this->request('artist', ['query' => sprintf('artist:"%s"', $this->escape($artist)), 'limit' => 1]);
        $first = $data['artists'][0] ?? null;
        $tags = ($first && ($first['score'] ?? 0) >= 85) ? $this->tagNames($first['tags'] ?? []) : [];

        return $this->artistTags[$key] = $tags;
    }

    protected function request(string $endpoint, array $query): array
    {
        $this->requestCount++;

        $wait = self::$lastRequestAt + self::MIN_GAP_US / 1e6 - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1e6));
        }
        self::$lastRequestAt = microtime(true);

        $response = Http::withHeaders(['User-Agent' => config('music.musicbrainz_agent')])
            ->timeout(15)
            ->get(self::BASE.$endpoint, $query + ['fmt' => 'json']);

        if ($response->status() === 503) {
            // Throttled — back off once and retry.
            sleep(3);
            self::$lastRequestAt = microtime(true);
            $response = Http::withHeaders(['User-Agent' => config('music.musicbrainz_agent')])
                ->timeout(15)
                ->get(self::BASE.$endpoint, $query + ['fmt' => 'json']);
        }

        if ($response->failed()) {
            Log::warning("MyMusic MusicBrainz {$endpoint} failed ({$response->status()})");

            return [];
        }

        return $response->json() ?? [];
    }

    /** @return array<string> Top tag names by vote count. */
    protected function tagNames(array $tags): array
    {
        usort($tags, fn ($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

        return array_slice(array_values(array_filter(array_map(
            fn ($t) => trim((string) ($t['name'] ?? '')), $tags
        ))), 0, 5);
    }

    protected function escape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
