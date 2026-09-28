# Changelog

All notable changes to **Manifold Apps** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The displayed version lives in `config/app.php` (`version`) and is shown in the app footer.

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
