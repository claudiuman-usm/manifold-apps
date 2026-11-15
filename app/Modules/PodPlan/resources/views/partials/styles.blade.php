{{-- PodPlan styles. The module wears the hub's own "Glass / Flow" look: it
     takes the hub's tokens (panels, text, radii, shadows) from app.css and
     only adds its sky palette and the pieces the hub does not have. Scoped to
     body.ctx-podplan and .pp so nothing leaks into the other tools. --}}
<style>
body.ctx-podplan {
    --accent: #0284c7; --accent-solid: #0369a1; --accent-2: #38bdf8; --accent-ink: #ffffff;
    --accent-soft: rgba(2, 132, 199, .14); --ring: rgba(3, 105, 161, .30);
    --page: #eef6fd; --orb-1: #7dd3fc; --orb-2: #a5b4fc; --orb-3: #99f6e4; --maxw: 1320px;

    --pp-ink: var(--text); --pp-muted: var(--text-2); --pp-label: #6f6d80;
    --pp-surface: #ffffff; --pp-ground: rgba(2, 132, 199, .06); --pp-canvas: rgba(255, 255, 255, .75);
    --pp-sheet: rgba(255, 255, 255, .93); --pp-line: var(--stroke); --pp-line-2: rgba(20, 18, 35, .16);
    --pp-accent: var(--accent); --pp-accent-ink: #075985; --pp-accent-soft: var(--accent-soft);
    --pp-live: #b4500a; --pp-live-ink: #8f3f06; --pp-live-soft: rgba(180, 80, 10, .12);
    --pp-ok: #0f8a5f; --pp-ok-soft: rgba(15, 138, 95, .14); --pp-bad: #b4232a; --pp-bad-soft: rgba(229, 72, 77, .12); --pp-axis: #7c3aed;
    --pp-table: rgba(20, 18, 35, .07); --pp-lamp: #ffe9a8; --pp-box: #16151d; --pp-on-box: #ffffff;
}
:root[data-theme="dark"] body.ctx-podplan {
    --accent: #38bdf8; --accent-solid: #38bdf8; --accent-ink: #04202e;
    --accent-soft: rgba(56, 189, 248, .16); --ring: rgba(56, 189, 248, .30); --page: #07121a;

    --pp-label: #9a98aa; --pp-surface: #14151b; --pp-ground: rgba(56, 189, 248, .07); --pp-canvas: rgba(255, 255, 255, .05);
    --pp-sheet: rgba(22, 24, 32, .95); --pp-line-2: rgba(255, 255, 255, .18); --pp-accent-ink: #bae6fd;
    --pp-live: #e8974c; --pp-live-ink: #f3b67c; --pp-live-soft: rgba(232, 151, 76, .16);
    --pp-ok: #35d29a; --pp-ok-soft: rgba(53, 210, 154, .16); --pp-bad: #ff6369; --pp-bad-soft: rgba(255, 99, 105, .16); --pp-axis: #b79cff;
    --pp-table: rgba(255, 255, 255, .08); --pp-lamp: #e9cf7a; --pp-box: #e8e8ee; --pp-on-box: #16151d;
}
body.ctx-podplan .content { padding-top: 28px; }

.pp { color: var(--text); font-size: .94rem; line-height: 1.5; }
.pp *, .pp *::before, .pp *::after { box-sizing: border-box; }
.pp h1 { font-size: 2.25rem; font-weight: 300; letter-spacing: -.03em; line-height: 1.1; margin: 0; }
.pp h2 { font-size: 1.1rem; font-weight: 400; letter-spacing: -.02em; margin: 0; }
.pp h3 { font-size: .95rem; font-weight: 600; letter-spacing: -.01em; margin: 0; }
.pp p { margin: 0; }
.pp a { color: var(--pp-accent-ink); font-weight: 600; }
.pp .pp-muted { color: var(--text-2); }
.pp .pp-small { font-size: .84rem; }
.pp [hidden] { display: none !important; }
.pp-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

/* ---- Module bar: breadcrumb + pill menu ---- */
.pp-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; margin-bottom: 22px; }
.pp-bar .crumbs { margin: 0; flex: 1 1 auto; min-width: 0; }
.pp .crumbs a { color: var(--text); }
.pp-ver { margin-left: 2px; padding: 2px 8px; border-radius: 999px; background: var(--panel); border: 1px solid var(--panel-border); color: var(--pp-label); font-size: .7rem; font-weight: 700; }
.pp-nav { display: inline-flex; padding: 3px; border-radius: 999px; background: var(--panel); border: 1px solid var(--panel-border); backdrop-filter: blur(var(--blur)); -webkit-backdrop-filter: blur(var(--blur)); }
.pp .pp-nav a { padding: 7px 16px; border-radius: 999px; color: var(--text-2); font-size: .85rem; font-weight: 500; white-space: nowrap; text-align: center; }
.pp .pp-nav a:hover { color: var(--text); }
.pp .pp-nav a.is-active { background: var(--accent-solid); color: var(--accent-ink); font-weight: 600; }

.pp-top { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 18px; margin-bottom: 22px; }
.pp-top .pp-grow { flex: 1 1 300px; min-width: 0; }
.pp-top h1 + p { margin-top: 4px; }

/* ---- Buttons, tags, switches ---- */
.pp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 38px; padding: 0 18px; border-radius: 999px; border: 1px solid var(--panel-border); background: var(--panel-strong); color: var(--text); font: inherit; font-size: .88rem; font-weight: 400; line-height: 1; text-decoration: none; cursor: pointer; white-space: nowrap; transition: box-shadow .16s, filter .14s; }
.pp a.pp-btn { color: var(--text); font-weight: 400; }
.pp-btn:hover { box-shadow: var(--shadow-sm); }
.pp-btn:disabled { opacity: .5; cursor: default; box-shadow: none; }
.pp-btn--primary, .pp a.pp-btn--primary { background: var(--accent-solid); border-color: transparent; color: var(--accent-ink); box-shadow: 0 6px 18px var(--ring); }
.pp-btn--primary:hover { filter: brightness(1.05); box-shadow: 0 10px 26px var(--ring); }
.pp-btn--live { background: var(--pp-live); border-color: transparent; color: #fff; }
.pp-btn--quiet { border-color: transparent; background: transparent; color: var(--text-2); }
.pp-btn--danger { border-color: transparent; background: transparent; color: var(--pp-bad); }
.pp-btn--danger:hover { background: var(--pp-bad-soft); box-shadow: none; }
.pp-btn--tight { min-height: 32px; padding: 0 14px; font-size: .82rem; }
.pp-btn--icon { width: 34px; min-height: 34px; padding: 0; }

.pp-tag { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: .72rem; font-weight: 700; line-height: 1.4; white-space: nowrap; background: var(--panel-strong); color: var(--text-2); border: 1px solid var(--stroke); }
.pp-tag--live { background: var(--pp-live-soft); color: var(--pp-live-ink); border-color: transparent; }
.pp-tag--rule { background: var(--accent-soft); color: var(--pp-accent-ink); border-color: transparent; }
.pp-tag--rented { background: transparent; color: var(--text); border-color: var(--pp-line-2); }
.pp-tag--short { background: var(--pp-bad-soft); color: var(--pp-bad); border-color: transparent; }
.pp-tag--ok { background: var(--pp-ok-soft); color: var(--pp-ok); border-color: transparent; }

.pp-seg { display: inline-flex; padding: 3px; border-radius: 999px; background: var(--panel); border: 1px solid var(--panel-border); }
.pp-seg button, .pp-seg label { min-height: 30px; min-width: 46px; padding: 0 14px; border: 0; border-radius: 999px; background: transparent; color: var(--text-2); font: inherit; font-size: .82rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.pp-seg input { position: absolute; opacity: 0; pointer-events: none; }
.pp-seg .is-on, .pp-seg label:has(input:checked) { background: var(--accent-solid); color: var(--accent-ink); font-weight: 600; }
.pp-seg .is-on.is-live, .pp-seg label.is-live:has(input:checked) { background: var(--pp-live); color: #fff; }

.pp-toggle { display: inline-flex; align-items: center; gap: 8px; min-height: 32px; font-size: .86rem; color: var(--text-2); cursor: pointer; white-space: nowrap; }
.pp-check input, .pp-toggle input { flex: none; width: 18px; height: 18px; margin: 0; cursor: pointer; appearance: none; -webkit-appearance: none; border: 1.5px solid var(--pp-line-2); border-radius: 6px; background: var(--field); display: grid; place-content: center; }
.pp-check input:checked, .pp-toggle input:checked { background: var(--pp-ok); border-color: var(--pp-ok); }
.pp-toggle input:checked { background: var(--accent-solid); border-color: var(--accent-solid); }
.pp-check input:checked::after, .pp-toggle input:checked::after { content: ""; width: 9px; height: 5px; border-left: 2px solid #fff; border-bottom: 2px solid #fff; transform: translateY(-1px) rotate(-45deg); }
.pp-check input:focus-visible, .pp-toggle input:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

/* ---- Panels ---- */
.pp-card { background: var(--panel); backdrop-filter: blur(var(--blur)) saturate(1.5); -webkit-backdrop-filter: blur(var(--blur)) saturate(1.5); border: 1px solid var(--panel-border); border-radius: var(--r-panel); box-shadow: var(--shadow-sm); padding: 18px 20px; }
.pp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
.pp-stack { display: flex; flex-direction: column; gap: 12px; }
.pp-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
.pp-between { justify-content: space-between; }
.pp-label { font-size: .8rem; font-weight: 700; color: var(--text-2); }
.pp-eyebrow { font-size: .68rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: var(--pp-label); }
.pp-big { font-size: 3rem; font-weight: 300; letter-spacing: -.03em; line-height: 1; font-variant-numeric: tabular-nums; }
.pp-big--sm { font-size: 1.75rem; }

.pp-progress { height: 8px; border-radius: 999px; background: var(--panel-strong); border: 1px solid var(--panel-border); overflow: hidden; }
.pp-progress > span { display: block; height: 100%; width: 0; border-radius: 999px; background: linear-gradient(90deg, var(--accent), var(--accent-2)); transition: width .3s; }

.pp-notice { padding: 10px 16px; border-radius: 16px; background: var(--accent-soft); color: var(--pp-accent-ink); display: flex; flex-direction: column; gap: 6px; font-size: .9rem; }
.pp-notice--live { background: var(--pp-live-soft); color: var(--pp-live-ink); }
.pp-notice--bad { background: var(--pp-bad-soft); color: var(--pp-bad); }
.pp-notice strong { font-weight: 600; }

/* ---- Forms ---- */
.pp-field { display: flex; flex-direction: column; gap: 5px; }
.pp-field > label { font-size: .8rem; font-weight: 700; color: var(--text-2); }
.pp-help { color: var(--text-2); font-size: .82rem; }
.pp-input, .pp select.pp-input, .pp textarea.pp-input { width: 100%; min-height: 38px; padding: 8px 13px; border-radius: 13px; border: 1px solid var(--panel-border); background: var(--field); color: var(--text); font: inherit; font-size: .9rem; }
.pp textarea.pp-input { min-height: 96px; resize: vertical; }
.pp-input--pill, .pp input[type="search"].pp-input { border-radius: 999px; padding-left: 16px; padding-right: 16px; }
.pp-input::placeholder { color: var(--pp-label); }
.pp-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px var(--ring); }
.pp-error { color: var(--pp-bad); font-size: .82rem; font-weight: 600; }
.pp-formgrid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }

/* ---- Plan ---- */
.pp-plan { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 18px; }
.pp-side { flex: 1 1 210px; max-width: 250px; min-width: 200px; display: flex; flex-direction: column; gap: 2px; padding: 16px 12px; }
.pp-side > h2, .pp-side > p, .pp-side > .pp-field, .pp-side > .pp-notice, .pp-side > .pp-row, .pp-side > h3, .pp-side > ul { margin-left: 8px; margin-right: 8px; }
.pp-side > p { margin-bottom: 6px; }
.pp-side > .pp-input { margin: 2px 4px 4px; width: calc(100% - 8px); }
.pp-stage { flex: 999 1 480px; min-width: 0; display: flex; flex-direction: column; gap: 12px; }
.pp-stagecard { padding: 12px 14px 14px; border-radius: var(--r-card); }
.pp-stagecard > .pp-row { padding: 2px 4px 10px; }
.pp-preview { flex: 1 1 260px; max-width: 320px; min-width: 250px; display: flex; flex-direction: column; gap: 2px; }
.pp-sheet-close { display: none; }
.pp-grouphead { margin: 10px 10px 2px; color: var(--pp-label); font-size: .66rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
.pp-gear { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; min-height: 34px; padding: 5px 10px; border: 0; border-radius: 11px; background: transparent; color: var(--text); font: inherit; font-size: .86rem; text-align: left; cursor: pointer; }
.pp-gear:hover { background: var(--panel-2); }
.pp-gear.is-on { background: var(--panel-strong); }
.pp-gear.is-selected { background: var(--accent-soft); }
.pp-gear .nm { min-width: 0; }
.pp-gear .nm small { display: block; color: var(--text-2); font-size: .74rem; }
.pp-gear .stat { flex: none; font-size: .74rem; font-weight: 700; color: var(--pp-accent-ink); white-space: nowrap; }
.pp-gear.is-on .stat { color: var(--pp-ok); }
.pp-canvas { border-radius: 16px; background: var(--pp-canvas); overflow: hidden; }
.pp-canvas svg { display: block; width: 100%; height: auto; touch-action: pan-x pan-y pinch-zoom; user-select: none; -webkit-user-select: none;
    background-image: radial-gradient(var(--pp-line-2) 1px, transparent 1px); background-size: 20px 20px; }
.pp-canvas [data-id] { cursor: grab; touch-action: none; }
.pp-canvas [data-id].is-drag { cursor: grabbing; }
.pp-canvas [data-axis] { cursor: grab; touch-action: none; }
.pp-canvas text { font-family: var(--font); font-weight: 600; fill: var(--text); pointer-events: none;
    paint-order: stroke; stroke: var(--pp-surface); stroke-width: 3px; stroke-linejoin: round; }
.pp-canvas text.sub { font-weight: 500; fill: var(--text-2); }
.pp-canvas text.num { font-weight: 800; fill: var(--accent-ink); stroke: none; }
.pp-canvas text.axis { font-weight: 800; fill: var(--pp-axis); }
.pp-canvas text.zone { letter-spacing: .09em; fill: var(--pp-label); stroke: none; font-weight: 800; }
.pp-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; min-height: 52px; padding: 8px 12px 8px 18px; border-radius: 26px; background: var(--panel); backdrop-filter: blur(var(--blur)) saturate(1.5); -webkit-backdrop-filter: blur(var(--blur)) saturate(1.5); border: 1px solid var(--panel-border); box-shadow: var(--shadow-sm); }
.pp-toolbar .pp-btn { background: var(--field); border-color: var(--stroke); }
.pp-toolbar .pp-btn--danger { background: transparent; border-color: transparent; }
.pp-toolname { font-size: .9rem; font-weight: 600; }
.pp-toolask { display: inline-flex; align-items: center; gap: 6px; font-size: .78rem; font-weight: 700; color: var(--text-2); }
.pp-toolask select { width: auto; min-width: 150px; max-width: 230px; min-height: 34px; padding: 4px 12px; border-radius: 999px; background: var(--field); border-color: var(--stroke); font-weight: 400; }
.pp-stepper { display: inline-flex; align-items: center; border-radius: 999px; border: 1px solid var(--stroke); background: var(--field); }
.pp-stepper button { width: 32px; height: 32px; border: 0; border-radius: 50%; background: transparent; color: var(--text); font: inherit; font-size: 1rem; cursor: pointer; }
.pp-angle { min-width: 38px; text-align: center; font-size: .84rem; font-weight: 600; font-variant-numeric: tabular-nums; }
.pp-shareplan { flex: none; max-width: 800px; margin: 0 auto; }
.pp-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; padding: 0 8px; color: var(--text-2); font-size: .8rem; }
.pp-legend span { display: inline-flex; align-items: center; gap: 6px; }
.pp-legend i { display: inline-block; }
.pp-sechead { margin-top: 12px; padding-bottom: 4px; border-bottom: 1px solid var(--stroke); font-size: .88rem; font-weight: 600; display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; }
.pp-prow { display: flex; align-items: center; gap: 8px; min-height: 26px; flex-wrap: wrap; font-size: .86rem; }
.pp-prow::before { content: ""; flex: none; width: 4px; height: 4px; border-radius: 50%; background: var(--text-3); }
.pp-prow .lb { flex: 1; min-width: 0; }
.pp-ask { padding: 8px 10px; border-radius: 14px; background: var(--accent-soft); display: flex; flex-direction: column; gap: 5px; margin: 4px 0; }
.pp-ask .q { font-size: .78rem; font-weight: 700; color: var(--pp-accent-ink); }
.pp-ask select { min-height: 34px; padding: 4px 12px; border-radius: 999px; background: var(--field); }
.pp-list { margin: 0; padding-left: 18px; font-size: .86rem; }
.pp-list li { margin: 2px 0; }
.pp-dock { display: none; }

/* ---- Checklist ---- */
.pp-hero { display: flex; flex-direction: column; gap: 14px; padding: 20px 24px; border-radius: var(--r-card); margin-bottom: 16px; }
.pp-cl { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 18px; }
.pp-cl-main { flex: 999 1 520px; min-width: 0; display: flex; flex-direction: column; gap: 14px; }
.pp-cl-side { flex: 1 1 260px; max-width: 320px; min-width: 250px; display: flex; flex-direction: column; gap: 14px; }
.pp-cl-grid { display: flex; align-items: flex-start; gap: 14px; }
.pp-cl-grid > .pp-card { flex: 1 1 auto; }
.pp-cl-col { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; gap: 14px; }
.pp-cl-grid .pp-card { padding: 14px 18px 6px; }
.pp-cardhead { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 8px; margin-bottom: 4px; }
.pp-cardhead h2 { font-size: .97rem; font-weight: 600; letter-spacing: -.01em; }
/* Tags drop under a long label instead of squeezing it. */
.pp-check { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; min-height: 36px; padding: 5px 0; border-top: 1px solid var(--stroke); cursor: pointer; font-size: .9rem; }
.pp-check .lb { flex: 1 1 120px; min-width: 0; }
.pp-check .lb small { display: block; color: var(--text-2); font-size: .78rem; }
.pp-check.is-packed .lb { color: var(--text-2); text-decoration: line-through; }
.pp-check .tags { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; justify-content: flex-end; margin-left: auto; }
.pp-check .tags:empty { display: none; }
.pp-addrow { display: flex; flex-wrap: wrap; gap: 6px; }
.pp-addrow .pp-input { flex: 1 1 130px; width: auto; }

/* ---- Inventory ---- */
.pp-inv { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 18px; }
.pp-rail { flex: 1 1 200px; max-width: 240px; min-width: 190px; padding: 12px 10px; display: flex; flex-direction: column; gap: 2px; }
.pp .pp-rail a { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 12px; color: var(--text); font-size: .88rem; font-weight: 400; }
.pp .pp-rail a:hover { background: var(--panel-2); }
.pp .pp-rail a.is-active { background: var(--accent-soft); color: var(--pp-accent-ink); font-weight: 600; }
.pp-rail a span { color: var(--pp-label); font-variant-numeric: tabular-nums; }
.pp-rail .pp-eyebrow { margin: 12px 12px 4px; }
.pp-inv-main { flex: 999 1 440px; min-width: 0; display: flex; flex-direction: column; gap: 14px; }
.pp-kind { padding: 0; overflow: hidden; }
.pp-kind > summary { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 12px; padding: 15px 20px 12px; cursor: pointer; list-style: none; }
.pp-kind > summary::-webkit-details-marker { display: none; }
.pp-kind > summary .meta { color: var(--pp-label); font-size: .82rem; }
.pp-kind > summary .grow { flex: 1; }
.pp-kind > summary .fold { color: var(--pp-label); font-size: .8rem; font-weight: 600; }
.pp-kind[open] > summary .fold .closed, .pp-kind:not([open]) > summary .fold .opened { display: none; }
.pp-brings { margin: 0 20px 4px; padding: 9px 14px; border-radius: 14px; background: var(--pp-ground); display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; font-size: .82rem; }
.pp-brings b { font-weight: 700; color: var(--pp-accent-ink); }
.pp-brings .it { padding: 1px 10px; border-radius: 999px; background: var(--panel-strong); border: 1px solid var(--panel-border); }
.pp-icols, .pp .pp-irow { display: grid; grid-template-columns: minmax(0, 1fr) 150px 40px minmax(0, 170px); gap: 12px; align-items: center; padding: 6px 20px; }
.pp-icols { padding-top: 10px; font-size: .66rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--pp-label); }
.pp .pp-irow { min-height: 48px; border-top: 1px solid var(--stroke); color: var(--text); font-weight: 400; }
.pp .pp-irow:hover { background: var(--panel-2); }
.pp .pp-irow.is-selected { background: var(--accent-soft); }
.pp-irow .nm { min-width: 0; }
.pp-irow .nm strong { display: block; font-size: .92rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pp-irow .nm small { display: block; font-size: .78rem; color: var(--pp-label); }
.pp-irow .qty, .pp-icols .qty { text-align: right; font-variant-numeric: tabular-nums; color: var(--text-2); }
.pp-irow .same { font-size: .82rem; color: var(--pp-label); }
.pp-detail { flex: 1 1 320px; max-width: 390px; min-width: 300px; padding: 22px; border-radius: var(--r-card); background: var(--panel-strong); box-shadow: var(--shadow); display: flex; flex-direction: column; gap: 16px; }
.pp-detail h2 { font-size: 1.35rem; }
.pp-detail .pp-part { display: flex; flex-direction: column; gap: 2px; padding-top: 14px; border-top: 1px solid var(--stroke); }
.pp-bucket { display: grid; grid-template-columns: 108px minmax(0, 1fr); gap: 10px; align-items: start; padding: 10px 0; border-top: 1px solid var(--stroke); }
.pp-bucket:first-of-type { border-top: 0; }
.pp-bucket .ttl { padding-top: 4px; font-size: .78rem; font-weight: 700; color: var(--text-2); }
.pp-bucket .body { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.pp-chip { display: inline-flex; align-items: center; gap: 2px; padding: 2px 4px 2px 11px; border-radius: 999px; background: var(--field); border: 1px solid var(--stroke); font-size: .82rem; }
.pp-chip button { width: 22px; height: 22px; border: 0; border-radius: 50%; background: transparent; color: var(--text-2); font: inherit; font-size: .95rem; line-height: 1; cursor: pointer; }
.pp-chip button:hover { background: var(--stroke); }
.pp-bucket .pp-addrow .pp-input { min-height: 32px; padding: 4px 12px; border-radius: 999px; font-size: .82rem; flex: 1 1 84px; min-width: 0; }
/* On the near-white detail panel a field needs a line of its own to be seen. */
.pp-detail .pp-input { border-color: var(--pp-line-2); }

/* ---- Phone ---- */
@media (max-width: 860px) {
    .pp-stage { order: -1; flex-basis: 100%; }
    .pp-side, .pp-preview, .pp-rail, .pp-detail, .pp-cl-side { max-width: none; }
}
@media (max-width: 760px) {
    body.ctx-podplan .content { padding: 18px 16px 40px; }
    .pp h1 { font-size: 1.6rem; }
    .pp-bar { margin-bottom: 14px; }
    .pp-bar .crumbs { font-size: .78rem; }
    .pp-nav { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); width: 100%; }
    .pp .pp-nav a { display: grid; place-items: center; min-height: 44px; padding: 0 4px; }
    .pp-top { margin-bottom: 14px; }
    /* Touch sizes: everything you tap is at least 44px tall. */
    .pp-btn, .pp-input, .pp select.pp-input, .pp-toolask select, .pp-ask select { min-height: 44px; }
    .pp-btn--tight { min-height: 44px; }
    .pp-btn--icon { width: 44px; }
    .pp-seg button, .pp-seg label { min-height: 38px; }
    .pp-stepper button { width: 44px; height: 44px; }
    .pp-check { min-height: 48px; font-size: .95rem; }
    .pp-check input, .pp-toggle input { width: 22px; height: 22px; }
    .pp-gear { min-height: 46px; font-size: .92rem; }
    .pp-chip button { width: 34px; height: 34px; }
    /* The kinds become a row of chips you swipe, so the gear itself is not pushed down the page. */
    .pp-rail { flex-direction: row; flex-wrap: nowrap; align-items: center; gap: 6px; padding: 8px; overflow-x: auto; scrollbar-width: none; }
    .pp-rail::-webkit-scrollbar { display: none; }
    .pp-rail .pp-eyebrow { display: none; }
    .pp .pp-rail a { flex: none; min-height: 44px; white-space: nowrap; border-radius: 999px; }
    .pp-icols { display: none; }
    .pp .pp-irow { grid-template-columns: minmax(0, 1fr) auto; row-gap: 2px; min-height: 56px; padding: 8px 16px; }
    .pp-irow .qty:empty { display: none; }
    .pp-kind > summary { padding: 14px 16px 10px; }
    .pp-brings { margin: 0 16px 4px; }
    .pp-hero { padding: 16px 18px; }

    /* Plan: the diagram stays put; gear, checklist and describe open as sheets
       from a bar pinned to the bottom. */
    .pp-plan { padding-bottom: 84px; }
    /* Room to scroll the diagram to the top while a sheet is open. */
    .pp-plan[data-sheet]:not([data-sheet=""]) { padding-bottom: 80vh; }
    .pp-mode-btn { display: none; }
    .pp-toolbar { border-radius: 22px; padding: 12px 14px; background: var(--panel-strong); }
    .pp-toolname { flex: 1 1 100%; }
    .pp-toolask { flex: 1 1 130px; flex-direction: column; align-items: stretch; gap: 3px; }
    .pp-toolask select { width: 100%; max-width: none; min-width: 0; border-radius: 14px; }
    .pp-plan .pp-side, .pp-plan .pp-preview { position: fixed; left: 0; right: 0; bottom: 0; z-index: 45; min-width: 0; max-height: 64vh; overflow-y: auto; overscroll-behavior: contain;
        border-radius: 26px 26px 0 0; border-bottom: 0; background: var(--pp-sheet); box-shadow: var(--shadow-pop);
        padding: 14px 14px calc(18px + env(safe-area-inset-bottom, 0px)); transform: translateY(105%); transition: transform .22s ease; visibility: hidden; }
    .pp-plan[data-sheet="gear"] .pp-side, .pp-plan[data-sheet="describe"] .pp-side, .pp-plan[data-sheet="checklist"] .pp-preview { transform: none; visibility: visible; }
    .pp-sheet-close { display: inline-flex; margin-left: auto; }
    .pp-dock { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; position: fixed; left: 0; right: 0; bottom: 0; z-index: 40;
        padding: 10px 16px calc(12px + env(safe-area-inset-bottom, 0px)); background: var(--pp-sheet); backdrop-filter: blur(var(--blur)) saturate(1.6); -webkit-backdrop-filter: blur(var(--blur)) saturate(1.6); border-top: 1px solid var(--stroke); }
    .pp-dock .pp-btn { min-height: 46px; padding: 0 8px; }
}
@media (prefers-reduced-motion: reduce) {
    .pp-plan .pp-side, .pp-plan .pp-preview, .pp-progress > span { transition: none; }
}
</style>
