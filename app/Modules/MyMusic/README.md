# My Music (`music` module)

Organizes the music in your YouTube **Liked videos** and turns filtered sets into
real YouTube playlists. Lives at `/music`, behind the hub login.

**Pipeline:** Sync (Liked → `music_videos`) → Parse (artist/title from raw titles)
→ Enrich (MusicBrainz: album, year, genres → `music_tracks`) → filter/play in the
library → create private YouTube playlists from any filter.

## Google Cloud setup (one-time)

1. Go to [console.cloud.google.com](https://console.cloud.google.com), create a
   project (e.g. `manifold-apps`).
2. **APIs & Services → Library** → enable **YouTube Data API v3**.
3. **APIs & Services → OAuth consent screen** → External → fill in the app name
   + your email. Add yourself under **Test users** (publishing is not needed for
   a single-user app; test-user refresh tokens keep working).
4. **APIs & Services → Credentials → Create credentials → OAuth client ID** →
   type **Web application**. Add both redirect URIs:
   - `http://manifold-apps.test/music/oauth/callback` (local, Herd)
   - `https://apps.manifold.ro/music/oauth/callback` (production)
5. Copy the client ID + secret into `.env`:

```
GOOGLE_CLIENT_ID=...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=...
```

Then open `/music` and hit **Connect Google account**. Tokens are stored
encrypted (APP_KEY) in `music_google_tokens`; Disconnect wipes them, and access
can also be revoked from [myaccount.google.com/permissions](https://myaccount.google.com/permissions).

## Enrichment (MusicBrainz)

Keyless but rate-limited to 1 request/second with a descriptive User-Agent
(`MUSIC_MB_AGENT`). Two ways to run it, sharing the same runner:

- **UI**: the "Enrich now" button loops small chunks (~8 lookups/request).
- **Cron** (production): run every minute; each run does ~40 lookups (≈45 s):

```
* * * * * /opt/alt/php84/usr/bin/php /home/manifold/repositories/manifold-apps/artisan music:enrich >/dev/null 2>&1
```

A second, daily cron pulls new Liked videos automatically (`music:sync`);
whatever it finds is parsed + enriched by the every-minute cron above:

```
0 4 * * * /opt/alt/php84/usr/bin/php /home/manifold/repositories/manifold-apps/artisan music:sync >/dev/null 2>&1
```

Locally: `php artisan music:sync` then `php artisan music:enrich --all`.
Manual fixes (✎ in the table) set `enrich_status=manual` and are never overwritten.

## YouTube quota

Default project quota is **10,000 units/day** (resets midnight Pacific). Costs:
`playlistItems.list` = 1/page of 50; `playlists.insert` = **50**;
`playlistItems.insert` = **50 per track** → ≈ **199 playlist tracks/day**.
Every call is logged to `music_quota_log`; playlist creation shows the estimate
first, pauses automatically on `quotaExceeded`, and resumes from where it left
off (Resume button on `/music/playlists`).

## Docked player

The player bar (`music::partials.player`, driven by `public/js/music-player.js`)
is shared by all three music pages and **survives navigation between them**:
links marked `data-pjax` swap only the `#music-page` container in place, so the
YouTube iframe is never reloaded and playback continues. The player owns the
queue (a copy of the rows playback started from), transport + keyboard
shortcuts, shuffle, the scrub bar, star rating of the current track, and play
counting; page scripts talk to it via `window.MusicPlayer` and `music:*`
document events. Page scripts re-run on every swap, so each starts by aborting
the previous page's document-level listeners (`window.musicPageAbort`).
Leaving the module (e.g. to the dashboard) is a normal full navigation and
stops playback.

## Play tracking, ratings & stats

Playing a track past ~30s (or half of a short one) records a **play** — the
docked player reads its own progress, so skips/previews don't inflate counts.
Each row has a 3-star **rating** (stored on the video). The **Stats** page
(`/music/stats`) derives everything locally (no external cost): top artists,
genre families, decades, most/least-played, and rediscovery buckets
(top-rated-but-unplayed "lost favourites"). An optional **AI taste analysis**
button sends the stats to Claude (reuses `ANTHROPIC_API_KEY` from Receipts; set
`MUSIC_AI_MODEL` to override) for a taste-profile narrative + tracks worth
revisiting — hidden if no key is set.

## Tables

`music_videos` (raw Liked items, insert-only sync, `is_music` + `embeddable`
flags) · `music_tracks` (parsed + enriched, 1:1 by `video_id`) ·
`music_playlists` + `music_playlist_items` (created playlists, remaining
`queue`, stored `filter_json` for Sync) · `music_google_tokens` (encrypted
OAuth) · `music_quota_log` (per-call quota spend, Pacific-dated) ·
`music_plays` (one row per counted listen, for time-based stats;
`play_count`/`last_played_at`/`rating` also denormalized on `music_videos`).
