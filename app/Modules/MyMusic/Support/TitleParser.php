<?php

namespace App\Modules\MyMusic\Support;

/**
 * Extracts artist + title from a raw YouTube video title.
 *
 * Handles "Artist - Title" (also – — |), "Title (Artist)", strips noise tags
 * like (Official Video) / [HD] / (Lyrics), and normalizes ft./feat. credits.
 * Also guesses whether the video is music at all (channel + title signals).
 */
class TitleParser
{
    /** Bracketed chunks containing any of these are junk tags, not content. */
    protected const TAG_PATTERN =
        '/\s*[(\[][^()\[\]]*\b(?:'
        .'official|oficial|officiel|offizielles|lyric|lyrics|audio|video|visuali[sz]er'
        .'|videoclip|music\s*video|hd|hq|4k|full\s*hd|explicit|remaster(?:ed)?'
        .'|out\s*now|premier[ea]|subtitrat|traducere|napisy|sub\s+espa[ñn]ol|color\s*coded'
        .')\b[^()\[\]]*[)\]]/iu';

    protected const FEAT_PATTERN = '/\s+(?:feat\.?|ft\.?|featuring)\s+(.+)$/iu';

    /** Same junk when it trails unbracketed: "… | Official Video", "… - Lyrics". */
    protected const TRAILING_TAG_PATTERN =
        '/\s*[|\-–—]\s*(?:official\s*(?:music\s*)?(?:video|audio)?|videoclip\s*oficial'
        .'|video\s*oficial|lyric\s*video|lyrics?|visuali[sz]er|audio|hd|hq|4k)\s*$/iu';

    /** @return array{artist:?string,title:string,is_music:bool} */
    public static function parse(string $raw, ?string $channel): array
    {
        $raw = trim($raw);

        // Real-world titles occasionally carry invalid UTF-8, which makes the
        // /u-flagged preg_* calls below fail (returning false/null) — strip
        // the bad bytes up front.
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        }

        if ($raw === '' || in_array($raw, ['Deleted video', 'Private video'], true)) {
            return ['artist' => null, 'title' => $raw ?: '—', 'is_music' => false];
        }

        $hadTags = (bool) (preg_match(self::TAG_PATTERN, $raw) || preg_match(self::TRAILING_TAG_PATTERN, $raw));
        $clean = trim((string) preg_replace(self::TAG_PATTERN, '', $raw));
        $clean = trim((string) preg_replace(self::TRAILING_TAG_PATTERN, '', $clean));
        $clean = (string) preg_replace('/\s{2,}/u', ' ', $clean);
        // NOT trim() with a charlist: trim is byte-based and its multibyte
        // quote chars share bytes with emoji, corrupting them.
        $clean = trim((string) preg_replace('/^["“”«»\s]+|["“”«»\s]+$/u', '', $clean));

        $isTopic = (bool) preg_match('/\s-\s*Topic$/iu', (string) $channel);
        $isVevo = (bool) preg_match('/vevo/iu', (string) $channel);

        $artist = null;
        $title = $clean !== '' ? $clean : $raw;
        $hadSeparator = false;
        $hadParenArtist = false;

        // "Artist - Title" (en/em dash and pipe variants). First separator wins.
        $parts = preg_split('/\s+[-–—|]\s+/u', $title, 2) ?: [];
        if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
            [$artist, $title] = [trim($parts[0]), trim($parts[1])];
            $hadSeparator = true;
        } elseif (preg_match('/^(.{2,})\s+\(([^()]{2,60})\)$/u', $title, $m)
            && ! preg_match('/\b(?:feat\.?|ft\.?|featuring|remix|edit|mix|cover|version)\b/iu', $m[2])) {
            // "Title (Artist)" — only when the parens aren't a musical qualifier.
            $title = trim($m[1]);
            $artist = trim($m[2]);
            $hadParenArtist = true;
        }

        // Move "feat. X" out of the artist into the title credit.
        if ($artist && preg_match(self::FEAT_PATTERN, $artist, $m)) {
            $artist = trim((string) preg_replace(self::FEAT_PATTERN, '', $artist));
            if (! preg_match('/\b(?:feat\.?|ft\.?|featuring)\b/iu', $title)) {
                $title .= ' (feat. '.trim($m[1]).')';
            }
        }

        if (! $artist) {
            $artist = self::artistFromChannel($channel);
        }

        $isMusic = $isTopic || $isVevo || $hadSeparator || $hadTags || $hadParenArtist;

        return ['artist' => $artist ?: null, 'title' => $title, 'is_music' => $isMusic];
    }

    /** Channel name minus " - Topic" / trailing "VEVO" — the auto-channel conventions. */
    public static function artistFromChannel(?string $channel): ?string
    {
        if (! $channel) {
            return null;
        }

        $artist = (string) preg_replace('/\s*-\s*Topic$/iu', '', $channel);
        $artist = (string) preg_replace('/\s*vevo$/iu', '', $artist);

        return trim($artist) ?: null;
    }
}
