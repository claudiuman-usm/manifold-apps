# Changelog

All notable changes to **Manifold Apps** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The displayed version lives in `config/app.php` (`version`) and is shown in the app footer.

## [1.14.0] - 2026-11-15

### Added
- **Change password** screen (`/account/password`, "Password" in the top bar). Asks for the current password, then the new one twice, and shows a confirmation when it is done. New passwords are at least 10 characters and must differ from the current one; attempts are limited to 6 a minute. Changing the password also retires any "remember me" cookie left on another device.

### Note
- There is still no "forgot password" by e-mail — this host has no mail. The change screen only works while signed in; a forgotten password is still a job for phpMyAdmin.

## [1.13.0] - 2026-11-15

### Added
- PodPlan 1.2.0: the **secret link now shows the plan** as well. The shared page has two tabs, Checklist and Plan. The Plan tab is the same drawing as the owner's Plan screen — gear, camera numbers and lenses, cables, FOH, the axis of action and its warning — to look at only: nothing can be moved, turned, added or removed from it. It follows the owner's changes within a few seconds, has its own "Field of view" tick box, and adding `#plan` to the link opens it straight on the plan.
- PodPlan: the checklist cards are laid out as **masonry** (on your Checklist screen and on the shared page). Cards stack in columns with no gaps under the short ones; the order still reads across the top, and each next card drops into the shortest column. One column on a phone.

### Changed
- PodPlan: the shared plan is sent with only what is drawn (positions, labels, numbers) — no inventory, no item details, no rental sources. Changing the plan still needs the login.
- PodPlan: the plan's drawing code now lives in one place (`partials/plan-canvas`), used by both the Plan screen and the shared page.

## [1.12.0] - 2026-11-15

### Added
- PodPlan 1.1.0: the **axis of action** (the 180° rule) on the plan. An "Axis" tick box next to "Field of view" shows a line that starts through the two people on set. Drag the line to move it, drag either end to turn it; with the line selected the toolbar turns it in 5° steps or puts it back through the people. The line and whether it is shown are saved with the project.
- PodPlan: a camera on the **wrong side of the axis** is ringed in red and named in a warning under the plan. The working side is where most cameras are; with one camera each side, the lowest-numbered camera decides.
- PodPlan: **camera numbers**. Every camera is numbered as it is placed (the lowest free number) and the number is drawn on the camera. Change it from the toolbar when the camera is selected — taking a number another camera has swaps the two — or take it off. Numbers are for the plan only and do not appear on the checklist.

### Changed
- PodPlan: on a numbered camera the **wireless mark** moves to the top-right corner of the camera, like a notification badge; a camera without a number keeps it in the middle.

### Database
- New migration `2026_11_15_000006`: `axis` on `podplan_projects`, `number` on `podplan_placements`; cameras already on a plan are numbered in the order they were placed. Run `/_deploy/migrate` after deploying.

## [1.11.0] - 2026-11-15

### Added
- **PodPlan** — a new tool (`/podplan`) for planning a shoot and packing for it. Create a project (name, type, date, location, people on camera, live mixing yes/no), lay the set out on a top-down plan, and the packing checklist builds itself from what is on the plan.
- PodPlan wears the **hub's own look**: glass panels over colour orbs, the hub's type and pill buttons, a sky-blue palette and dark mode, with the hub breadcrumb and a pill menu on every screen.
- PodPlan on a **phone**: the whole diagram fits the width with larger labels and tap areas; gear, checklist and describe open as sheets from a bar at the bottom, and the diagram stays in view above them; a selected camera's lens, connection, turning and field of view sit in a sheet under the diagram.
- PodPlan: **Plan** screen — tap gear from your inventory to place it, drag to move, turn cameras. Cable lines follow the gear type (XLR mics run to the audio mixer, cameras run to the video mixer while live mixing is on, wireless mics draw nothing). Each camera's label shows the lens chosen for it.
- PodPlan: **FOH**, a mixer corner on the plan (bottom right) where video and audio mixers land by default, stacked with their names beside them. Cables no longer cut across the set: each one runs from its gear straight to the edge of the canvas, follows the edge, and comes in to the mixer from there, in its own lane.
- PodPlan: the camera **field of view** follows the lens — a longer focal length draws a narrower cone, and a speed booster widens it again (worked out from the lens and adapter names, for the Blackmagic 4K sensor). A "Field of view" tick box on the plan hides the cones.
- PodPlan: **lights turn and throw a beam** like cameras — a new light starts aimed at the table — and the angle of any camera or light can be set by hand from the plan toolbar (− / +), with Auto to hand it back to the lens. (New migration: `fov` on `podplan_placements`.)
- PodPlan: the checklist now includes **the gear itself** as a row to tick (the camera, the light, the mixer), not only what goes with it, and has a **Rented** filter showing everything that is not yours and who it comes from.
- PodPlan: the lens is chosen from a **dropdown** on the plan — in the checklist preview, and in the toolbar under the plan as soon as a camera is selected, so there is no hunting for it in the list. The two stay in sync.
- PodPlan: a camera's link to the video mixer can be **wireless**. With live mixing on, a Connection choice follows the lens (Cable by default): Cable puts the HDMI cable on the checklist, Wireless puts a wireless video transmitter and receiver there instead and draws no cable line. Both are new accessory kinds, with one of each added to the inventory. (New migration.)
- PodPlan: every camera and every light brings a **power cord** on the checklist (for lights the old "Power cable" line was renamed). A camera on a wireless link shows a small **wireless mark**, and so does the video mixer in FOH that receives it. (New migration.)
- PodPlan: a **search** box on the Inventory screen, filtering the table as you type by name, type, who it is rented from, or what goes with it.
- PodPlan has its **own version** (`module.php`, starting at 1.0.0), shown next to its name in the module menu and at the foot of the shared packing page.
- PodPlan: **kits with several pieces** — a wireless kit with two lavs is placed twice (A and B, one per person) and still packs as one kit. New wireless gear starts at 2 pieces; any plan gear can be given more in Inventory.
- PodPlan: **search** above the gear list on the plan, filtering as you type by name, type or who it is rented from.
- PodPlan: the checklist can be sorted **By gear** or **By kind** (all tripods together, all stands, all lenses), each row saying which gear it is for.
- PodPlan: **module navigation** on every screen — Projects, Plan, Checklist, Inventory. Plan and Checklist open the project you are in, or the one you opened last.
- PodPlan: **rule engine** — every gear type has preset rules in four buckets (always / if live mixing / if no live mixing / ask me every time), and any item can override them. Accessories (tripods, light stands, SSDs, lenses, adapters, monitors, FetHeads) are counted pools: your own units are used first, rented ones only when those run out, and a "short" warning shows when a pool is empty.
- PodPlan: **Checklist** screen — the only place items get ticked, and only for packing: lenses are chosen on the Plan, never here. Progress bar, All / To pack filter, a note of any choice still open, your own extra items, and a Reset button so a project can be reused. Ticks survive a rebuild and reset when gear is removed and put back.
- PodPlan: **Pack together** — a secret link to one project's checklist. Anyone holding it can tick items with you, with no login, and it opens nothing else. Replace the link or stop sharing at any time.
- PodPlan: **Inventory** screen — kinds of gear down the side, each kind a panel that says once what every item of it brings, and rows that only say what an item does differently ("No Light stand", "+ Mic preamp"). A kind's own rules are editable, an item can differ or be made the rule for its kind, and project-wide general rules have their own view.
- PodPlan: **Describe** — describe the shoot in words and Claude places gear from your inventory to match (structured output, Claude Opus 5.5, server-side). It never places gear you do not own; that comes back as a list. Keep, Undo, or ask for a change. Hidden when no `ANTHROPIC_API_KEY` is set.
- PodPlan: your gear list is seeded as the starting inventory. (New migrations: `podplan_*` tables and the inventory seed — run `/_deploy/migrate` after pulling.)

## [1.10.1] - 2026-11-13

### Changed
- My Music: the ↗ links now open normal YouTube instead of YouTube Music — the "open original" button on a track row (`youtube.com/watch`) and the playlist link on the Playlists page (`youtube.com/playlist`). Labels/tooltips updated accordingly (EN/RO).

## [1.10.0] - 2026-10-02

### Added
- My Music: **manual tempo marks** — SLOW / FAST pill buttons on the docked player mark the playing track as you listen; clicking the active mark clears it. Stored on the video row like the rating. (New migration: `tempo` on `music_videos`.)
- My Music: a **Tempo filter** in the sidebar (Any / Slow / Fast / Not marked) — "Not marked" is handy for sweeping through what's left to classify. Included in the URL state and in saved playlist filters (the star-rating filter is now saved with playlists too).
- My Music: a **"Go to song" link** on the docked player scrolls the library to the playing row; from the Playlists/Stats pages it first swaps back to the library.

### Removed
- My Music: the keyboard-hints line on the docked player ("space play/pause · ← prev · → next") — the Go to song link sits there now. The shortcuts themselves still work.

## [1.9.0] - 2026-10-01

### Added
- My Music: **unliking a video on YouTube now marks it non-music** in the library. The daily `music:sync` cron walks the whole Liked playlist, so anything no longer present gets `is_music = false` and an `unliked_at` stamp (ratings and play history are kept). Re-liking the video flips it back to music on the next sync. Only unlike-swept rows flip back — videos marked non-music by the title parser or by hand stay non-music. The browser-driven chunked sync never sweeps (it only sees part of the playlist), and an empty API response is ignored as a safety guard. Cron output now reports re-liked/unliked counts. (New migration: `unliked_at` on `music_videos`.)

### Removed
- My Music: the Music / All videos / Non-music filter dropdown is gone — with unliking as the way to mark non-music, the library now always shows music only. The per-row "move back to Music" button went with it (re-like the video on YouTube instead).

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
