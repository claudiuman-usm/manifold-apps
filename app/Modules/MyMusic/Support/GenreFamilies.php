<?php

namespace App\Modules\MyMusic\Support;

/**
 * Rolls MusicBrainz's ~380 raw genre tags up into a short set of browsable
 * umbrella families, so the filter answers "play all my Folk" in one click.
 *
 * Keyword-based, NOT a fixed lookup: a family owns word-stems, and any tag
 * containing one is bucketed automatically — so new tags from future
 * enrichment ("post-rock", "future funk") self-classify with no maintenance.
 * A tag can match several families (e.g. "pop rock" → Pop + Rock), which is
 * good for rediscovery. Junk / non-genre tags are dropped.
 */
class GenreFamilies
{
    /** Non-genre tags to ignore entirely (nationalities, activities, MB cruft). */
    protected const DROP = [
        'british', 'american', 'uk', 'usa', 'english', 'romanian', 'romanian band',
        'french', 'german', 'canadian', 'australian', 'swedish', 'norwegian',
        'seen live', 'favourites', 'favorites', 'ambiguous isrcs', 'spotify',
        'beautiful', 'catchy', 'male vocalists', 'female vocalists', 'melancholic',
    ];

    /**
     * Ordered family → keyword-stems. First-match order matters only for the
     * few genuinely ambiguous stems; most tags match one family cleanly.
     * A tag matches a family if any stem is a substring of the tag.
     *
     * @var array<string, list<string>>
     */
    protected const FAMILIES = [
        'Hip-Hop'    => ['hip hop', 'hip-hop', 'rap', 'trap', 'grime', 'drill', 'boom bap'],
        'Electronic' => ['electro', 'techno', 'house', 'trance', 'ambient', 'downtempo',
                          'trip hop', 'trip-hop', 'idm', 'dubstep', 'drum and bass', 'dnb',
                          'synth', 'edm', 'dance', 'big beat', 'breakbeat', 'garage', 'chillwave',
                          'vaporwave', 'glitch', 'industrial'],
        'Metal'      => ['metal', 'metalcore', 'grindcore', 'djent', 'hardcore'],
        'Soul / R&B' => ['soul', 'r&b', 'rnb', 'funk', 'motown', 'disco', 'neo soul', 'gospel'],
        'Jazz'       => ['jazz', 'bebop', 'swing', 'bossa', 'big band'],
        'Blues'      => ['blues'],
        'Country'    => ['country', 'bluegrass', 'americana', 'honky tonk'],
        'Folk'       => ['folk', 'singer-songwriter', 'acoustic'],
        'Classical'  => ['classical', 'orchestra', 'baroque', 'opera', 'symphony', 'choral'],
        'World'      => ['reggae', 'ska', 'dub', 'latin', 'afrobeat', 'afro', 'celtic',
                          'flamenco', 'bossa nova', 'world', 'cumbia', 'k-pop', 'j-pop'],
        // Pop before Rock so bare "pop" wins; both still match "pop rock".
        'Pop'        => ['pop'],
        'Rock'       => ['rock', 'punk', 'grunge', 'shoegaze', 'britpop', 'new wave',
                          'post-punk', 'emo', 'indie', 'alternative', 'psychedelic'],
    ];

    /**
     * All families a set of raw tags belongs to (deduped, in FAMILIES order).
     *
     * @param  iterable<string>  $tags
     * @return list<string>
     */
    public static function familiesFor(iterable $tags): array
    {
        $matched = [];

        foreach ($tags as $tag) {
            $tag = mb_strtolower(trim((string) $tag));

            if ($tag === '' || in_array($tag, self::DROP, true)) {
                continue;
            }

            foreach (self::FAMILIES as $family => $stems) {
                foreach ($stems as $stem) {
                    if (str_contains($tag, $stem)) {
                        $matched[$family] = true;
                        break;
                    }
                }
            }
        }

        return array_keys($matched);
    }

    /** @return list<string> The family names, in canonical display order. */
    public static function all(): array
    {
        return array_keys(self::FAMILIES);
    }
}
