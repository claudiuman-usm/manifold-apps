# Changelog

All notable changes to **Manifold Apps** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The displayed version lives in `config/app.php` (`version`) and is shown in the app footer.

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
