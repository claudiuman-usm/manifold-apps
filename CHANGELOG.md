# Changelog

All notable changes to **Manifold Apps** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The displayed version lives in `config/app.php` (`version`) and is shown in the app footer.

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
