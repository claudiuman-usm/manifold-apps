# Changelog

All notable changes to **Manifold Apps** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The displayed version lives in `config/app.php` (`version`) and is shown in the app footer.

## [1.8.6] - 2026-09-29

### Changed
- My Music: the ▶ row button is gone — **click anywhere on a row to play it** (the pointer cursor hints at it); the open/edit/stars controls keep their own behavior.

### Fixed
- My Music: the sticky table header still offset itself 64px to clear the old sticky topbar, leaving it floating over the rows — it now pins to the top edge.

## [1.8.5] - 2026-09-29

### Changed
- My Music: the Artist filter dropdown is now ordered by number of tracks (descending, ties alphabetical) — same as the Genre dropdown — instead of alphabetically.

## [1.8.4] - 2026-09-29

### Changed
- The top header no longer sticks to the viewport — it scrolls away with the page (hub-wide). The My Music pinned sidebar group now pins 16px from the top again, since it no longer needs to clear the header.

## [1.8.3] - 2026-09-29

### Fixed
- My Music: the back-to-top button could sit behind the open docked player — its offset was a hard-coded guess of the bar's height. The player script now measures the bar's real rendered height (ResizeObserver + resize) and places the button just above it.

## [1.8.2] - 2026-09-29

### Added
- My Music: **back-to-top button** — a round glass button in the bottom-right of all music pages, appearing after ~400px of scroll; smooth-scrolls to the top and lifts above the docked player when it's open.

### Changed
- My Music: the Library sidebar's first three islands (Actions, Search, Filters) now **stay pinned** while the table scrolls; the connection/status island scrolls away normally. On short viewports the pinned group scrolls internally; in the single-column mobile layout nothing is pinned.

## [1.8.1] - 2026-09-29

### Changed
- My Music: friendlier star ratings — the text ★ glyphs are replaced with rounded-corner SVG stars (soft outline when unset, filled amber when set, gentle grow on hover) everywhere they appear: the library table, the docked player and the stats lists.

## [1.8.0] - 2026-09-29

### Added
- My Music: **the docked player survives navigation** between the Library, Playlists and Stats pages. Links between them now swap only the page content in place (`#music-page`), while the player bar — extracted to a shared partial + `public/js/music-player.js` — keeps its YouTube iframe untouched, so the music never stops. Back/Forward work too; leaving the module is a normal full load.

### Changed
- My Music: the Library and Playlists pages now share one player implementation (`window.MusicPlayer`) instead of two separate ones; the playlists-page player gains the artwork cover, star rating, shuffle toggle and keyboard shortcuts it lacked. The player keeps its own copy of the play queue, so next/prev/auto-advance keep working while you browse Stats or Playlists.
- My Music: after finishing a playlist creation, the redirect to the playlists page keeps the music playing.

## [1.7.1] - 2026-09-29

### Added
- My Music: star rating in the docked player — rate (or clear) the current track without hunting for its row; stays in sync with the table.

### Fixed
- My Music: the highlighted now-playing row is scrolled to the middle of the screen instead of ending up hidden under the docked player.

## [1.7.0] - 2026-09-28

### Added
- My Music: **play tracking, ratings & listening stats**.
  - Passive **play counting** — a track counts as played once listened past ~30s (or half of a short one), read from the player's own progress so skips don't inflate it. Stored as a `music_plays` log plus denormalized counters on the video.
  - **3-star ratings** per row (click the top star again to clear), with new Rated / ★★★ / ★★+ / Unrated / Never-played filters and sort-by rating/plays.
  - **Stats page** (`/music/stats`): totals, top artists, genre-family and decade breakdowns, most-played, top-rated, and rediscovery-focused **Lost Favourites** (rated but long unplayed). All derived locally, no external cost.
  - Optional **AI taste analysis** — sends the stats to Claude (reuses `ANTHROPIC_API_KEY`; `MUSIC_AI_MODEL` to override) for a taste-profile narrative + tracks worth revisiting; hidden when no key is set.

### Changed
- My Music: genres filter now collapses MusicBrainz's ~380 raw tags into ~12 browsable umbrella families via keyword rules (new tags self-classify; junk/nationality tags dropped); raw tags still shown per row.
- My Music: dropdown panels are opaque and layer correctly above sibling sidebar islands (fixes see-through / behind-island rendering in dark mode).

## [1.6.2] - 2026-09-28

### Changed
- My Music: larger docked-player transport buttons — round 46px prev/next/shuffle/close with a prominent 56px filled play/pause, scaling down slightly on mobile.

## [1.6.1] - 2026-09-28

### Added
- My Music: a **scrub bar** in the docked player — current/total time and a draggable seek slider on both the library and playlists players. Driven by the YouTube player's own `infoDelivery` messages (robust across API versions) and seeks via the iframe postMessage command API.

## [1.6.0] - 2026-09-28

### Added
- My Music: **daily auto-sync** — new `music:sync` artisan command fetches fresh Liked videos unattended (run from a daily cron); the existing every-minute enrichment cron then identifies whatever arrives, making the whole pipeline hands-off. Sync logic extracted into a shared `LikedSyncer` used by both the browser sync and the command.

## [1.5.2] - 2026-09-28

### Added
- My Music: a ♪ "Move back to Music" action on each row — shown only in the **Non-music** view, so wrongly auto-flagged tracks can be restored without any risk of accidentally hiding music from the normal view.

## [1.5.1] - 2026-09-28

### Fixed
- My Music: the shared filter script is now served through the app (`assets/music-filter.js` route) like the CSS — on the cPanel shim, plain `public/js/…` files 404, which broke sync/filtering in production ("Cannot read properties of undefined (reading 'filter')").

## [1.5.0] - 2026-09-28

### Added
- **My Music** — new module (`/music`, pink card): organizes the music in your YouTube Liked videos and turns filtered sets into real YouTube playlists.
  - **Connect + sync**: Google OAuth (web flow, tokens stored encrypted, revocable), chunked insert-only sync of the Liked playlist ("LL") with live progress; every YouTube API call's quota cost is logged against the 10,000-unit daily budget (shown in the status strip, Pacific-midnight reset).
  - **Parse + enrich**: artist/title parsed from raw titles ("Artist - Title" dash/pipe variants, "Title (Artist)", tag stripping, ft./feat. handling, "- Topic"/VEVO channel fallback); non-music videos auto-flagged with a per-row ♪ toggle. MusicBrainz enrichment (album, year, genres) at the polite 1 req/sec — runs from the UI in chunks or unattended via the `music:enrich` cron; manual edits (✎) are never overwritten.
  - **Library**: filterable table (search, multi-artist, multi-genre, year range, status, music/non-music) with sortable columns and filters persisted in the URL; docked YouTube player with auto-advance through the current filter, shuffle, keyboard controls (space/←/→) and automatic skip + flag of non-embeddable videos; per-row jump to YouTube Music.
  - **Playlists**: create a private YouTube playlist from any filter (quota estimate + warning up front, chunked inserts with progress, automatic pause on quotaExceeded with Resume); Sync re-runs the stored filter and appends only missing tracks; playlists page with YT Music link and in-app playlist player.
  - **Sidebar layout**: search, filters, actions, and sync/enrichment status live in sidebar islands; the track table gets the full main column, with cover art per row, pinned row-actions, and columns that collapse to fit the available width (container queries).
  - The docked player shows the track **artwork** instead of the video (the video keeps playing underneath), and switching the **theme no longer reloads the page mid-playback** — the music keeps going.
  - Module docs in `app/Modules/MyMusic/README.md` (Google Cloud setup, cron line, quota math).

## [1.4.2] - 2026-08-09

### Fixed
- Flow-er run mode: the current (active) step's label was low-contrast in dark mode — the step button now uses the theme text colour.
- Flow-er run mode: switching theme (light/dark) or locale from the topbar no longer triggers the "leave a running flow" warning — those toggles just reload the same page.

## [1.4.1] - 2026-08-09

### Changed
- Flow-er run mode: the pause/play button now sits after the Next button and is smaller, reading as a secondary action.

## [1.4.0] - 2026-08-09

### Added
- Flow-er run mode: **Pause / Play** the step timer. A round pale-green icon button pauses the clock (banking the time so far) and toggles to a play icon to resume. Paused time is never counted. Hitting Next while paused advances normally and the next step resumes running.

## [1.3.2] - 2026-08-09

### Changed
- Flow-er: run history is now responsive. On phones the wide runs × steps matrix becomes one card per run (step name → time rows) plus an averages card; the full table stays on wider screens.

## [1.3.1] - 2026-08-09

### Fixed
- Flow-er: the run-complete (summary) screen no longer shows the Resume / "In progress" option — it's a finished run. Resume stays only on the revisit surfaces (template list and run history).

## [1.3.0] - 2026-08-09

### Added
- Flow-er: leaving a running flow (tab close, refresh, breadcrumb/nav links, browser back) now warns first. The run's own actions (next/back/check/cancel) don't trigger the warning.
- Flow-er: an in-progress flow is kept, so returning to its template offers **Resume** (with an "In progress" pill) or **Start new**. Starting new discards the in-progress flow after a confirm. Applies on the template list, run history, and run summary.

### Changed
- Flow-er: a template now keeps at most one in-progress run — starting a new run discards the previous unfinished one.

## [1.2.0] - 2026-08-09

### Added
- Flow-er run mode: **Back a step**. Reopen the previous step to make adjustments; its timer resumes and any new time is added onto what it had already recorded. Banking preserves the step you were on (the frontier) — re-checking the reopened step jumps you straight back there with its timer resumed. Back moves one step at a time and is hidden on the first step.

## [1.1.1] - 2026-08-09

### Changed
- Flow-er run mode: hovering a checklist step now shows only a pale-green outline and check mark (no fill), so a hover preview can't be mistaken for a completed (solid green) step.

## [1.1.0] - 2026-08-09

### Added
- Flow-er: the nudge toast now dims the page behind it with a slight scrim overlay so the prompt stands out. The scrim is non-blocking (page stays interactive) and respects reduced-motion.
- Started this changelog; footer version bumped to 1.1.0.

## [1.0] - Initial

- Manifold Apps hub with auto-discovered modules (Flow-er, Receipts).
