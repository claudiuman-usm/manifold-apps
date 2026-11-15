@extends('layouts.app')
@section('title', $project->name)
@section('bodyClass', 'ctx-podplan')
@push('head') @include('podplan::partials.styles') @endpush

@section('content')
<div class="pp" id="pp-plan">
    @include('podplan::partials.nav', ['active' => 'plan', 'project' => $project])
    <div class="pp-top">
        <div class="pp-grow">
            <h1>{{ $project->name }}</h1>
            <p class="pp-muted">
                {{ __('podplan::messages.shoot_types.'.$project->shoot_type) }}
                @if ($project->shoot_date) · {{ $project->shoot_date->translatedFormat('j M Y') }} @endif
                @if ($project->location) · {{ $project->location }} @endif
                · <a href="{{ route('podplan.projects.edit', $project) }}">{{ __('podplan::messages.plan.edit_details') }}</a>
            </p>
        </div>
        <div class="pp-row">
            <span class="pp-label" id="pp-live-label">{{ __('podplan::messages.form.live') }}</span>
            <div class="pp-seg" id="pp-live" role="group" aria-labelledby="pp-live-label">
                <button type="button" data-live="1" class="is-live">{{ __('podplan::messages.form.yes') }}</button>
                <button type="button" data-live="0">{{ __('podplan::messages.form.no') }}</button>
            </div>
        </div>
        <button type="button" class="pp-btn pp-mode-btn" id="pp-mode"></button>
    </div>

    <div class="pp-plan" id="pp-planwrap" data-sheet="">
        <aside class="pp-side pp-card" id="pp-side"></aside>

        <section class="pp-stage">
            <div class="pp-card pp-stagecard">
                <div class="pp-row pp-between">
                    <h2>{{ __('podplan::messages.plan.top_down') }}</h2>
                    <label class="pp-toggle"><input type="checkbox" id="pp-fov" checked>{{ __('podplan::messages.plan.fov') }}</label>
                </div>
                <div class="pp-canvas">
                    <svg id="pp-svg" viewBox="0 0 {{ $state['canvas']['w'] }} {{ $state['canvas']['h'] }}" role="img"
                         aria-label="{{ __('podplan::messages.plan.top_down') }}">
                        <g id="pp-zone"></g>
                        <g id="pp-cables"></g>
                        <g id="pp-symbols"></g>
                    </svg>
                </div>
            </div>
            <div class="pp-toolbar" id="pp-toolbar"></div>
            <div class="pp-legend">
                <span><i style="width:14px;height:10px;border-radius:2px;background:var(--pp-accent);"></i>{{ __('podplan::messages.plan.legend_camera') }}</span>
                <span><i style="width:12px;height:12px;border-radius:50%;background:var(--pp-lamp);border:2px solid var(--pp-ink);"></i>{{ __('podplan::messages.plan.legend_light') }}</span>
                <span><i style="width:18px;border-top:2px dashed var(--pp-live);"></i>{{ __('podplan::messages.plan.legend_hdmi') }}</span>
                <span><i style="width:18px;border-top:2px solid var(--pp-ok);"></i>{{ __('podplan::messages.plan.legend_xlr') }}</span>
                <span><svg width="18" height="13" viewBox="0 0 18 13" aria-hidden="true"><circle cx="9" cy="10.7" r="1.7" style="fill:var(--pp-muted)"/><path d="M5 7.7 A5.6 5.6 0 0 1 13 7.7" style="fill:none;stroke:var(--pp-muted);stroke-width:1.8;stroke-linecap:round"/><path d="M1.6 4.3 A10.4 10.4 0 0 1 16.4 4.3" style="fill:none;stroke:var(--pp-muted);stroke-width:1.8;stroke-linecap:round"/></svg>{{ __('podplan::messages.plan.legend_wireless') }}</span>
            </div>
        </section>

        <aside class="pp-preview pp-card" id="pp-preview"></aside>

        {{-- Phone only: the diagram stays in view and these open as sheets over its lower part. --}}
        <nav class="pp-dock" id="pp-dock" aria-label="{{ __('podplan::messages.nav.plan') }}">
            <button type="button" class="pp-btn pp-btn--primary" data-sheet="gear">{{ __('podplan::messages.plan.add_gear') }}</button>
            <button type="button" class="pp-btn" data-sheet="checklist">{{ __('podplan::messages.plan.checklist') }} · <span id="pp-dock-count">0</span></button>
            <button type="button" class="pp-btn" data-sheet="describe">{{ __('podplan::messages.describe.heading') }}</button>
        </nav>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var T = {
        plan: @json(__('podplan::messages.plan')),
        tags: @json(__('podplan::messages.tags')),
        describe: @json(__('podplan::messages.describe')),
        itemsOne: @json(trans_choice('podplan::messages.plan.items', 1)),
        itemsMany: @json(trans_choice('podplan::messages.plan.items', 5, ['count' => ':count'])),
        liveOne: @json(trans_choice('podplan::messages.describe.live_note', 1)),
        liveMany: @json(trans_choice('podplan::messages.describe.live_note', 5, ['count' => ':count']))
    };
    var URLS = {
        placements: @json(route('podplan.plan.add', $project)),
        live: @json(route('podplan.plan.live', $project)),
        answers: @json(route('podplan.plan.answer', $project)),
        describe: @json(route('podplan.plan.describe', $project)),
        keep: @json(route('podplan.plan.keep', $project)),
        undo: @json(route('podplan.plan.undo', $project)),
        checklist: @json(route('podplan.checklist', $project))
    };
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;
    var AI = @json($aiEnabled);

    var state = @json($state);
    var selected = null;
    var mode = (AI && /[?&]mode=describe/.test(location.search)) ? 'describe' : 'gear';
    var busy = false;
    var describeError = @json(session('pp_error'));
    var draft = @json((string) $project->description);
    var changeDraft = '';
    var query = '';
    // Whether the camera cones are drawn is a viewing preference, kept on this device.
    var fovOn = true;
    try { fovOn = localStorage.getItem('podplan.fov') !== '0'; } catch (err) {}

    // On a phone the whole diagram is fitted to the width (about half size), so
    // labels are drawn larger and tap areas wider to stay readable and grabbable.
    var narrow = window.matchMedia ? window.matchMedia('(max-width: 760px)') : {matches: false};
    function LS() { return narrow.matches ? 1.7 : 1; }

    var svg = document.getElementById('pp-svg');
    var gCables = document.getElementById('pp-cables');
    var gSymbols = document.getElementById('pp-symbols');
    var side = document.getElementById('pp-side');
    var preview = document.getElementById('pp-preview');
    var toolbar = document.getElementById('pp-toolbar');
    var modeBtn = document.getElementById('pp-mode');
    var W = state.canvas.w, H = state.canvas.h, SIZES = state.canvas.sizes;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[c];
        });
    }
    function fmt(str, vars) {
        return String(str).replace(/:([a-z]+)/g, function (m, k) { return vars[k] != null ? vars[k] : m; });
    }
    function find(id) { return state.placements.find(function (p) { return p.id === id; }); }
    function size(p) { return SIZES[p.symbol] || SIZES.box; }
    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

    function api(method, url, body) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest'},
            body: body ? JSON.stringify(body) : undefined
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (s) {
            state = s;
            if (selected && !find(selected)) selected = null;
            render();
            return s;
        });
    }

    // ---------------------------------------------------------------- canvas

    // The little "radio waves" mark for a wireless video link: on the camera that
    // sends and on the mixer that receives. Drawn upright whatever way the gear is turned.
    function waves(cx, cy, color) {
        var st = 'fill:none;stroke:' + color + ';stroke-width:1.8;stroke-linecap:round';
        return '<g pointer-events="none"><circle cx="' + cx + '" cy="' + (cy + 4.2) + '" r="1.7" style="fill:' + color + '"/>' +
            '<path d="M' + (cx - 4) + ' ' + (cy + 1.2) + ' A5.6 5.6 0 0 1 ' + (cx + 4) + ' ' + (cy + 1.2) + '" style="' + st + '"/>' +
            '<path d="M' + (cx - 7.4) + ' ' + (cy - 2.2) + ' A10.4 10.4 0 0 1 ' + (cx + 7.4) + ' ' + (cy - 2.2) + '" style="' + st + '"/></g>';
    }

    function symbol(p) {
        var rot = p.rot || 0;
        switch (p.symbol) {
            case 'cam':
                // The cone opens to the lens's angle of view: a long lens draws a narrow one.
                var half = 118 * Math.tan(((p.fov || 52) / 2) * Math.PI / 180);
                return '<g transform="rotate(' + rot + ' 20 14)">' +
                    (fovOn ? '<polygon points="20,2 ' + (20 - half).toFixed(1) + ',-116 ' + (20 + half).toFixed(1) + ',-116" pointer-events="none" style="fill:var(--pp-accent);fill-opacity:.14"/>' : '') +
                    '<rect width="40" height="28" rx="6" style="fill:var(--pp-accent)"/>' +
                    '<rect x="14" y="-6" width="12" height="8" rx="2" style="fill:var(--pp-accent-ink)"/></g>' +
                    (p.wireless ? waves(20, 13, 'var(--accent-ink)') : '');
            case 'mic':
                return '<circle cx="8" cy="8" r="6" style="fill:var(--pp-surface);stroke:var(--pp-ink);stroke-width:4"/>';
            case 'wireless':
                return '<circle cx="8" cy="8" r="6.5" style="fill:var(--pp-accent-soft);stroke:var(--pp-ink);stroke-width:2.5;stroke-dasharray:3 2.2"/>';
            case 'light':
                // A light throws a beam the same way a camera sees: turned, and as wide as its angle.
                var beam = 104 * Math.tan(((p.fov || 60) / 2) * Math.PI / 180);
                return '<g transform="rotate(' + rot + ' 17 17)">' +
                    (fovOn ? '<polygon points="17,17 ' + (17 - beam).toFixed(1) + ',-87 ' + (17 + beam).toFixed(1) + ',-87" pointer-events="none" style="fill:var(--pp-lamp);fill-opacity:.38"/>' : '') +
                    '</g><circle cx="17" cy="17" r="15.5" style="fill:var(--pp-lamp);stroke:var(--pp-ink);stroke-width:3"/>';
            case 'recorder':
                return '<rect width="' + size(p)[0] + '" height="' + size(p)[1] + '" rx="5" style="fill:var(--pp-box)"/>' +
                    '<circle cx="9" cy="' + (size(p)[1] / 2) + '" r="3" style="fill:var(--pp-ok)"/>';
            case 'person':
                return '<circle cx="16" cy="16" r="16" style="fill:var(--pp-ink)"/>';
            case 'table':
                return '<rect width="200" height="80" rx="12" style="fill:var(--pp-table);stroke:var(--pp-line-2)"/>';
            default:
                return '<rect width="' + size(p)[0] + '" height="' + size(p)[1] + '" rx="5" style="fill:var(--pp-box)"/>' +
                    (p.wireless_rx ? waves(size(p)[0] / 2, size(p)[1] / 2 - 1, 'var(--pp-on-box)') : '');
        }
    }

    function renderSymbols() {
        var rank = {furniture: 0, person: 1, gear: 2};
        var list = state.placements.slice().sort(function (a, b) { return rank[a.kind] - rank[b.kind]; });
        var h = '';
        var k = LS(), hit = narrow.matches ? 82 : 44;
        var f = 'style="font-size:' + (12 * k) + 'px"', fs = 'style="font-size:' + (11.5 * k) + 'px"';
        list.forEach(function (p) {
            var s = size(p), w = s[0], ht = s[1];
            var above = p.symbol === 'mic' || p.symbol === 'wireless';
            h += '<g data-id="' + p.id + '" transform="translate(' + p.x + ',' + p.y + ')">';
            // A generous invisible hit area, so small symbols are easy to grab on a phone.
            h += '<rect x="' + Math.min(-8, (w - hit) / 2) + '" y="' + Math.min(-8, (ht - hit) / 2) + '" width="' + Math.max(w + 16, hit) +
                 '" height="' + Math.max(ht + 16, hit) + '" fill="transparent"/>';
            h += symbol(p);
            if (selected === p.id) {
                h += '<rect x="-6" y="-6" width="' + (w + 12) + '" height="' + (ht + 12) + '" rx="8" pointer-events="none" ' +
                     'style="fill:none;stroke:var(--pp-accent);stroke-width:' + (2 * k) + ';stroke-dasharray:5 4"/>';
            }
            if (p.label && (p.symbol === 'box' || p.symbol === 'recorder')) {
                // Mixers and other boxes are named beside them, so a stack of them stays readable.
                var fits = p.x + w + 8 + p.label.length * 6.6 * k < W;
                h += '<text ' + f + ' x="' + (fits ? w + 6 : -6) + '" y="' + (ht / 2 + 4 * k) + '" text-anchor="' + (fits ? 'start' : 'end') + '">' + esc(p.label) + '</text>';
            } else if (p.label) {
                var ly = above ? -8 : ht + 4 + 12 * k;
                h += '<text ' + f + ' x="' + (w / 2) + '" y="' + ly + '" text-anchor="middle">' + esc(p.label) + '</text>';
                if (p.sub) h += '<text class="sub" ' + fs + ' x="' + (w / 2) + '" y="' + (ly + 14 * k) + '" text-anchor="middle">' + esc(p.sub) + '</text>';
            }
            h += '</g>';
        });
        gSymbols.innerHTML = h;
        renderCables();
    }

    // FOH, the mixer corner: where mixers land by default and where every cable ends up.
    function renderZone() {
        var z = state.canvas.zone;
        if (!z) return;
        document.getElementById('pp-zone').innerHTML =
            '<rect x="' + z[0] + '" y="' + z[1] + '" width="' + z[2] + '" height="' + z[3] + '" rx="12" ' +
            'style="fill:var(--pp-ground);stroke:var(--pp-accent);stroke-opacity:.45;stroke-dasharray:4 4"/>' +
            '<text class="zone" style="font-size:' + (10 * LS()) + 'px" text-anchor="end" x="' + (z[0] + z[2] - 12) + '" y="' + (z[1] + 7 + 10 * LS()) + '">' +
            esc(T.plan.mixer_corner.toUpperCase()) + '</text>';
    }

    /*
     * A cable never cuts across the set. It leaves its gear straight for the
     * edge of the canvas, follows the edge, and comes in to the mixer from
     * there. Each cable gets its own lane (inset) so runs lie side by side
     * like a loom instead of on top of each other. Of the four edges each end
     * could use, the pair that gives the shortest run wins.
     */
    function route(S, M, inset) {
        var L = inset, Tp = inset, R = W - inset, B = H - inset, w = R - L, h = B - Tp, per = 2 * (w + h);
        function proj(p, edge) {
            var x = clamp(p.x, L, R), y = clamp(p.y, Tp, B);
            return edge === 0 ? {x: x, y: Tp} : edge === 1 ? {x: R, y: y} : edge === 2 ? {x: x, y: B} : {x: L, y: y};
        }
        // Position along the perimeter, clockwise from the top-left corner.
        function along(p, edge) {
            return edge === 0 ? p.x - L : edge === 1 ? w + (p.y - Tp) : edge === 2 ? w + h + (R - p.x) : 2 * w + h + (B - p.y);
        }
        var corners = [{t: 0, x: L, y: Tp}, {t: w, x: R, y: Tp}, {t: w + h, x: R, y: B}, {t: 2 * w + h, x: L, y: B}];
        var best = null;
        for (var es = 0; es < 4; es++) {
            var p1 = proj(S, es), d1 = Math.abs(S.x - p1.x) + Math.abs(S.y - p1.y), t1 = along(p1, es);
            for (var em = 0; em < 4; em++) {
                var p2 = proj(M, em), d2 = Math.abs(M.x - p2.x) + Math.abs(M.y - p2.y), t2 = along(p2, em);
                var cw = ((t2 - t1) % per + per) % per, ccw = per - cw, total = d1 + Math.min(cw, ccw) + d2;
                if (!best || total < best.total) best = {total: total, p1: p1, p2: p2, t1: t1, t2: t2, cw: cw <= ccw, span: Math.min(cw, ccw)};
            }
        }
        var pts = [S, best.p1];
        // The corners passed on the way round, in the order they are met.
        var met = corners.map(function (c) {
            var d = best.cw ? ((c.t - best.t1) % per + per) % per : ((best.t1 - c.t) % per + per) % per;
            return {c: c, d: d};
        }).filter(function (m) { return m.d > 0.01 && m.d < best.span - 0.01; })
          .sort(function (a, b) { return a.d - b.d; });
        met.forEach(function (m) { pts.push(m.c); });
        pts.push(best.p2, M);
        return pts.map(function (p) { return Math.round(p.x * 10) / 10 + ',' + Math.round(p.y * 10) / 10; }).join(' ');
    }

    function renderCables() {
        var h = '';
        state.cables.forEach(function (c, i) {
            var a = find(c.from), b = find(c.to);
            if (!a || !b) return;
            var sa = size(a), sb = size(b);
            var pts = route({x: a.x + sa[0] / 2, y: a.y + sa[1] / 2}, {x: b.x + sb[0] / 2, y: b.y + sb[1] / 2}, 7 + (i % 9) * 3.5);
            h += '<polyline points="' + pts + '" style="fill:none;stroke-linejoin:round;' +
                (c.kind === 'hdmi' ? 'stroke:var(--pp-live);stroke-width:2;stroke-dasharray:6 5' : 'stroke:var(--pp-ok);stroke-width:1.6') + '"/>';
        });
        gCables.innerHTML = h;
    }

    function toSvg(e) {
        var pt = svg.createSVGPoint();
        pt.x = e.clientX; pt.y = e.clientY;
        return pt.matrixTransform(svg.getScreenCTM().inverse());
    }

    var drag = null;
    svg.addEventListener('pointerdown', function (e) {
        var g = e.target.closest('[data-id]');
        if (!g) { select(null); return; }
        var p = find(+g.getAttribute('data-id'));
        if (!p) return;
        var pt = toSvg(e);
        drag = {id: p.id, el: g, dx: pt.x - p.x, dy: pt.y - p.y, sx: e.clientX, sy: e.clientY, moved: false, pid: e.pointerId};
        try { g.setPointerCapture(e.pointerId); } catch (err) {}
    });
    svg.addEventListener('pointermove', function (e) {
        if (!drag || e.pointerId !== drag.pid) return;
        if (!drag.moved && Math.hypot(e.clientX - drag.sx, e.clientY - drag.sy) < 5) return;
        var p = find(drag.id);
        if (!p) return;
        drag.moved = true;
        drag.el.classList.add('is-drag');
        var pt = toSvg(e), s = size(p);
        p.x = Math.round(clamp(pt.x - drag.dx, 0, W - s[0]));
        p.y = Math.round(clamp(pt.y - drag.dy, 0, H - s[1]));
        drag.el.setAttribute('transform', 'translate(' + p.x + ',' + p.y + ')');
        renderCables();
        e.preventDefault();
    });
    function endDrag(e) {
        if (!drag || e.pointerId !== drag.pid) return;
        var d = drag, p = find(d.id);
        drag = null;
        d.el.classList.remove('is-drag');
        if (!p) return;
        if (d.moved) {
            api('PATCH', URLS.placements + '/' + p.id, {x: p.x, y: p.y});
        } else if (p.kind === 'gear') {
            select(selected === p.id ? null : p.id);
        }
    }
    svg.addEventListener('pointerup', endDrag);
    svg.addEventListener('pointercancel', endDrag);

    function select(id) {
        selected = id;
        renderSymbols();
        renderToolbar();
        if (mode === 'gear' && document.getElementById('pp-gear-list')) renderGearList();
    }

    function renderToolbar() {
        var p = selected ? find(selected) : null;
        if (!p) {
            toolbar.innerHTML = '<span class="pp-muted pp-small">' + esc(T.plan.selected_none) + '</span>';
            return;
        }
        var h = '<span class="pp-toolname">' + esc(p.title) +
                (p.provider ? ' <span class="pp-tag pp-tag--rented">' + esc(fmt(T.plan.rented, {name: p.provider})) + '</span>' : '') + '</span>';
        // Its lens is set right here, so there is no hunting for the camera in the checklist panel.
        var section = state.checklist.sections.find(function (s) { return s.item_id === p.item_id; });
        (section ? section.prompts : []).forEach(function (q) {
            var id = 'pp-tool-ask-' + q.placement_id + '-' + q.question.replace(/[^a-z0-9]+/gi, '-');
            h += '<label class="pp-toolask" for="' + id + '"><span>' + esc(q.question) + '</span>' + askSelect(q, id) + '</label>';
        });
        h += '<span style="flex:1"></span>';
        if (p.role === 'camera' || p.role === 'light') {
            var icon = function (d) {
                return '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + d + '</svg>';
            };
            h += '<button type="button" class="pp-btn pp-btn--icon" data-rot="-15" aria-label="' + esc(T.plan.rotate_left) + '" title="' + esc(T.plan.rotate_left) + '">' +
                 icon('<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/>') + '</button>' +
                 '<button type="button" class="pp-btn pp-btn--icon" data-rot="15" aria-label="' + esc(T.plan.rotate_right) + '" title="' + esc(T.plan.rotate_right) + '">' +
                 icon('<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/>') + '</button>' +
                 // The angle can be set by hand; Auto hands it back to the lens (or the standard beam).
                 '<span class="pp-stepper" role="group" aria-label="' + esc(T.plan.fov) + '" title="' + esc(T.plan.fov) + '">' +
                 '<button type="button" data-fov="-5" aria-label="' + esc(T.plan.narrower) + '">&minus;</button>' +
                 '<span class="pp-angle">' + Math.round(p.fov) + '&deg;</span>' +
                 '<button type="button" data-fov="5" aria-label="' + esc(T.plan.wider) + '">+</button></span>' +
                 (p.fov_manual ? '<button type="button" class="pp-btn pp-btn--tight" data-fov-auto="1">' + esc(T.plan.fov_auto) + '</button>' : '');
        }
        h += '<button type="button" class="pp-btn pp-btn--danger pp-btn--tight" data-remove="1">' + esc(T.plan.remove) + '</button>';
        toolbar.innerHTML = h;
    }
    toolbar.addEventListener('change', answerFrom);
    toolbar.addEventListener('click', function (e) {
        var p = selected ? find(selected) : null;
        if (!p) return;
        var rot = e.target.closest('[data-rot]');
        if (rot) { api('PATCH', URLS.placements + '/' + p.id, {rotation: (p.rot || 0) + (+rot.getAttribute('data-rot'))}); return; }
        var fov = e.target.closest('[data-fov]');
        if (fov) { api('PATCH', URLS.placements + '/' + p.id, {fov: clamp(Math.round(p.fov) + (+fov.getAttribute('data-fov')), 5, 160)}); return; }
        if (e.target.closest('[data-fov-auto]')) { api('PATCH', URLS.placements + '/' + p.id, {fov_auto: true}); return; }
        if (e.target.closest('[data-remove]')) { selected = null; api('DELETE', URLS.placements + '/' + p.id); }
    });

    // --------------------------------------------------------------- sidebar

    // Shown only on a phone, where the side panels are sheets.
    var closeBtn = '<button type="button" class="pp-btn pp-btn--quiet pp-btn--tight pp-sheet-close" data-sheet-close="1">' + @json(__('podplan::messages.plan.done')) + '</button>';

    function renderSide() {
        modeBtn.textContent = mode === 'gear' ? T.plan.describe_instead : T.plan.back_to_gear;
        if (mode === 'describe') { renderDescribe(); return; }

        var typing = document.activeElement && document.activeElement.id === 'pp-search';
        side.innerHTML = '<div class="pp-row"><h2>' + esc(T.plan.my_gear) + '</h2>' + closeBtn + '</div><p class="pp-muted pp-small">' + esc(T.plan.my_gear_help) + '</p>' +
            '<input type="search" class="pp-input" id="pp-search" autocomplete="off" placeholder="' + esc(T.plan.search) + '" aria-label="' +
            esc(T.plan.search) + '" value="' + esc(query) + '"><div id="pp-gear-list"></div>';
        if (typing) {
            var box = document.getElementById('pp-search');
            box.focus();
            box.setSelectionRange(query.length, query.length);
        }
        renderGearList();
    }

    // Fold case and accents so "rode" finds "RØDE" and "achitei" finds "Achiței".
    function fold(str) {
        return String(str || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ø/g, 'o');
    }

    // The gear list filters as you type; every word has to be found somewhere in
    // the item's name, its type or who it is rented from.
    function renderGearList() {
        var words = fold(query).split(/\s+/).filter(Boolean), h = '';
        state.gear.forEach(function (group) {
            var items = group.items.filter(function (it) {
                var hay = fold(it.name + ' ' + it.short + ' ' + group.label + ' ' + (it.provider || ''));
                return words.every(function (w) { return hay.indexOf(w) !== -1; });
            });
            if (!items.length) return;
            h += '<div class="pp-grouphead">' + esc(group.label) + '</div>';
            items.forEach(function (it) {
                var ids = it.placement_ids || [], on = ids.length > 0;
                h += '<button type="button" class="pp-gear' + (on ? ' is-on' : '') + (ids.indexOf(selected) !== -1 ? ' is-selected' : '') +
                     '" data-item="' + it.id + '" aria-pressed="' + on + '" title="' + esc(it.name) + '"><span class="nm">' + esc(it.short || it.name);
                if (it.provider) h += '<small>' + esc(fmt(T.plan.rented, {name: it.provider})) + '</small>';
                (it.double || []).forEach(function (name) {
                    h += '<small style="color:var(--pp-live-ink)">' + esc(fmt(T.plan.double, {label: it.name, project: name})) + '</small>';
                });
                // A kit with several pieces counts them: "1/2".
                var stat = it.units > 1 ? ids.length + '/' + it.units : (on ? T.plan.on_plan : T.plan.add);
                h += '</span><span class="stat"' + (it.units > 1 && ids.length < it.units ? ' style="color:var(--pp-accent-ink)"' : '') + '>' + esc(stat) + '</span></button>';
            });
        });
        document.getElementById('pp-gear-list').innerHTML = h || '<p class="pp-muted pp-small" style="margin-top:8px;">' + esc(T.plan.no_match) + '</p>';
    }

    function renderDescribe() {
        var D = T.describe, ai = state.ai, h = '<div class="pp-row"><h2>' + esc(D.heading) + '</h2>' + closeBtn + '</div><p class="pp-muted pp-small">' + esc(D.help) + '</p>';
        if (!AI) { side.innerHTML = h + '<div class="pp-notice">' + esc(D.off) + '</div>'; return; }
        if (describeError) h += '<div class="pp-notice pp-notice--bad">' + esc(describeError) + '</div>';

        if (ai && state.can_undo) {
            h += '<div class="pp-notice"><strong>' + esc(D.prompt) + '</strong><span>' + esc(ai.prompt) + '</span></div>';
            if (ai.notes) h += '<p class="pp-small">' + esc(ai.notes) + '</p>';
            h += '<h3 style="margin-top:6px;">' + esc(D.placed) + '</h3>';
            h += ai.placed && ai.placed.length
                ? '<ul class="pp-list">' + ai.placed.map(function (n) { return '<li>' + esc(n) + '</li>'; }).join('') + '</ul>'
                : '<p class="pp-muted pp-small">' + esc(D.none_placed) + '</p>';
            if (ai.missing && ai.missing.length) {
                h += '<div class="pp-notice pp-notice--live"><strong>' + esc(D.missing) + '</strong><ul class="pp-list">' +
                     ai.missing.map(function (n) { return '<li>' + esc(n) + '</li>'; }).join('') + '</ul></div>';
            }
            var liveRows = 0;
            state.checklist.sections.forEach(function (s) { s.rows.forEach(function (r) { if (r.live) liveRows++; }); });
            state.checklist.general.forEach(function (r) { if (r.live) liveRows++; });
            if (state.project.live && liveRows) {
                h += '<p class="pp-small" style="color:var(--pp-live-ink)">' + esc(liveRows === 1 ? T.liveOne : fmt(T.liveMany, {count: liveRows})) + '</p>';
            }
            h += '<div class="pp-row"><button type="button" class="pp-btn pp-btn--primary" data-act="keep"' + (busy ? ' disabled' : '') + '>' + esc(D.keep) + '</button>' +
                 '<button type="button" class="pp-btn" data-act="undo"' + (busy ? ' disabled' : '') + '>' + esc(D.undo) + '</button></div>';
            h += '<div class="pp-field" style="margin-top:8px;"><label for="pp-change">' + esc(D.change) + '</label>' +
                 '<textarea class="pp-input" id="pp-change" placeholder="' + esc(D.change_placeholder) + '">' + esc(changeDraft) + '</textarea></div>' +
                 '<button type="button" class="pp-btn" data-act="change"' + (busy ? ' disabled' : '') + '>' + esc(busy ? D.working : D.change_button) + '</button>';
        } else {
            h += '<div class="pp-field"><label for="pp-prompt">' + esc(D.prompt) + '</label>' +
                 '<textarea class="pp-input" id="pp-prompt" style="min-height:150px;">' + esc(draft) + '</textarea></div>' +
                 '<button type="button" class="pp-btn pp-btn--primary" data-act="generate"' + (busy ? ' disabled' : '') + '>' +
                 esc(busy ? D.working : D.generate) + '</button>';
        }
        side.innerHTML = h;
    }

    function runDescribe(prompt) {
        if (busy || !prompt.trim()) return;
        busy = true; describeError = null; renderSide();
        api('POST', URLS.describe, {prompt: prompt}).then(function (s) {
            describeError = s.error || null;
            if (!s.error) { changeDraft = ''; }
        }).catch(function () {
            describeError = T.describe.errors.failed;
        }).then(function () { busy = false; renderSide(); });
    }

    side.addEventListener('input', function (e) {
        if (e.target.id === 'pp-search') { query = e.target.value; renderGearList(); return; }
        if (e.target.id === 'pp-prompt') draft = e.target.value;
        if (e.target.id === 'pp-change') changeDraft = e.target.value;
    });
    side.addEventListener('click', function (e) {
        var gear = e.target.closest('[data-item]');
        if (gear) {
            var id = +gear.getAttribute('data-item'), item = null;
            state.gear.forEach(function (g) { g.items.forEach(function (i) { if (i.id === id) item = i; }); });
            if (!item) return;
            var ids = item.placement_ids || [];
            if (ids.length >= item.units) {
                // Everything of it is placed: step through its pieces, or let go of a single one.
                var at = ids.indexOf(selected);
                select(ids.length === 1 && at === 0 ? null : ids[(at + 1) % ids.length]);
                return;
            }
            api('POST', URLS.placements, {gear_item_id: id}).then(function () {
                var fresh = state.placements.filter(function (p) { return p.item_id === id && ids.indexOf(p.id) === -1; });
                if (fresh.length) select(fresh[fresh.length - 1].id);
            });
            return;
        }
        var act = e.target.closest('[data-act]');
        if (!act || busy) return;
        switch (act.getAttribute('data-act')) {
            case 'generate': runDescribe(draft); break;
            case 'change': runDescribe(changeDraft); break;
            case 'keep': api('POST', URLS.keep); break;
            case 'undo': api('POST', URLS.undo); break;
        }
    });
    modeBtn.addEventListener('click', function () {
        mode = mode === 'gear' ? 'describe' : 'gear';
        renderSide();
    });

    // --------------------------------------------------------------- preview

    function tags(r) {
        var h = '';
        if (r.live) h += '<span class="pp-tag pp-tag--live">' + esc(T.tags.live) + '</span>';
        if (r.rule) h += '<span class="pp-tag pp-tag--rule">' + esc(T.tags.rule) + '</span>';
        if (r.rented) h += '<span class="pp-tag pp-tag--rented">' + esc(fmt(T.plan.rented, {name: r.rented})) + '</span>';
        if (r.short) h += '<span class="pp-tag pp-tag--short">' + esc(T.tags.short) + '</span>';
        return h;
    }
    // Under its own heading the gear itself reads as what it is ("Camera"), not its name again.
    function row(r) { return '<div class="pp-prow"><span class="lb">' + esc(r.self ? r.kind : r.label) + '</span>' + tags(r) + '</div>'; }

    // The dropdown for an "ask me" question (the lens). The same control is used
    // in the checklist preview and in the toolbar of the selected gear.
    function askSelect(p, id) {
        // A question with a default (Connection: Cable) is always answered, so it has no empty choice.
        var h = '<select class="pp-input" id="' + id + '" data-pl="' + p.placement_id + '" data-q="' + esc(p.question) + '">' +
                (p.required ? '' : '<option value="">' + esc(T.plan.choose_option) + '</option>');
        p.options.forEach(function (o) {
            var on = p.chosen && (o.item_id != null ? p.chosen.item_id === o.item_id : p.chosen.value === o.value);
            h += '<option value="' + (o.item_id != null ? 'i:' + o.item_id : 'v:' + esc(o.value)) + '"' + (on ? ' selected' : '') + '>' +
                 esc(o.label) + (o.provider ? ' · ' + esc(o.provider) : '') + '</option>';
        });
        return h + '</select>';
    }
    // Choosing from it answers the question; "Choose…" clears the answer again.
    function answerFrom(e) {
        var sel = e.target.closest('select[data-pl]');
        if (!sel) return;
        var body = {placement_id: +sel.getAttribute('data-pl'), question: sel.getAttribute('data-q'), gear_item_id: null, value: null};
        if (sel.value.indexOf('i:') === 0) body.gear_item_id = +sel.value.slice(2);
        else if (sel.value.indexOf('v:') === 0) body.value = sel.value.slice(2);
        api('POST', URLS.answers, body);
    }

    function renderPreview() {
        var c = state.checklist;
        var h = '<div class="pp-row"><h2>' + esc(T.plan.checklist) + '</h2><span class="pp-muted pp-small" style="margin-left:auto;">' +
                esc(c.total === 1 ? T.itemsOne : fmt(T.itemsMany, {count: c.total})) + '</span>' + closeBtn.replace('pp-sheet-close"', 'pp-sheet-close" style="margin-left:0;"') + '</div>' +
                '<p class="pp-muted pp-small">' + esc(T.plan.preview_note) + ' <a href="' + URLS.checklist + '" style="font-weight:600;">' +
                esc(T.plan.checklist) + '</a>.</p>';

        c.warnings.forEach(function (w) {
            if (w.type === 'need_mixer') {
                h += '<div class="pp-notice pp-notice--live" style="margin-top:8px;"><strong>' + esc(T.plan.need_mixer) + '</strong>' +
                     (w.item_id ? '<button type="button" class="pp-btn pp-btn--live" style="align-self:flex-start;" data-mixer="' + w.item_id + '">' + esc(T.plan.add_mixer) + '</button>' : '') + '</div>';
            } else if (w.type === 'short') {
                h += '<div class="pp-notice pp-notice--bad" style="margin-top:8px;">' + esc(fmt(T.plan.short, {label: w.label, count: w.count})) + '</div>';
            } else if (w.type === 'double') {
                h += '<div class="pp-notice pp-notice--live" style="margin-top:8px;">' + esc(fmt(T.plan.double, {label: w.label, project: w.project})) + '</div>';
            }
        });

        c.sections.forEach(function (s) {
            h += '<div class="pp-sechead">' + esc(s.title) +
                 (s.provider ? '<span class="pp-tag pp-tag--rented">' + esc(fmt(T.plan.rented, {name: s.provider})) + '</span>' : '') + '</div>';
            s.rows.forEach(function (r) { h += row(r); });
            s.prompts.forEach(function (p) {
                var id = 'pp-ask-' + p.placement_id + '-' + p.question.replace(/[^a-z0-9]+/gi, '-');
                h += '<div class="pp-ask"><label class="q" for="' + id + '">' + esc(fmt(T.plan.change, {question: p.question, name: s.title})) + '</label>' +
                     askSelect(p, id) + '</div>';
            });
        });

        if (c.general.length) {
            h += '<div class="pp-sechead">' + esc(T.plan.general) + '</div>';
            c.general.forEach(function (r) { h += row(r); });
        }
        if (c.manual.length) {
            h += '<div class="pp-sechead">' + esc(T.plan.manual) + '</div>';
            c.manual.forEach(function (m) { h += '<div class="pp-prow"><span class="lb">' + esc(m.label) + '</span></div>'; });
        }
        if (!c.sections.length) h += '<p class="pp-muted" style="margin-top:12px;">' + esc(T.plan.empty_preview) + '</p>';
        preview.innerHTML = h;
    }

    preview.addEventListener('click', function (e) {
        var mixer = e.target.closest('[data-mixer]');
        if (mixer) { api('POST', URLS.placements, {gear_item_id: +mixer.getAttribute('data-mixer')}); return; }
    });

    preview.addEventListener('change', answerFrom);

    // ------------------------------------------------------------------ live

    var liveSeg = document.getElementById('pp-live');
    function renderLive() {
        liveSeg.querySelectorAll('button').forEach(function (b) {
            var on = (b.getAttribute('data-live') === '1') === !!state.project.live;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on);
        });
    }
    liveSeg.addEventListener('click', function (e) {
        var b = e.target.closest('[data-live]');
        if (b) api('PATCH', URLS.live, {live: b.getAttribute('data-live') === '1'});
    });

    var fovBox = document.getElementById('pp-fov');
    fovBox.checked = fovOn;
    fovBox.addEventListener('change', function () {
        fovOn = fovBox.checked;
        try { localStorage.setItem('podplan.fov', fovOn ? '1' : '0'); } catch (err) {}
        renderSymbols();
    });

    var wrap = document.getElementById('pp-planwrap');
    // A sheet never hides the diagram: the page scrolls the diagram to the top
    // and the sheet takes only the room that is left under it.
    function sheet(name) {
        wrap.setAttribute('data-sheet', name || '');
        if (name === 'gear' || name === 'describe') { mode = name; renderSide(); }
        var card = document.querySelector('.pp-stagecard');
        if (name && narrow.matches && card) {
            var room = Math.max(230, window.innerHeight - card.offsetHeight - 14);
            side.style.maxHeight = preview.style.maxHeight = room + 'px';
            window.scrollTo(0, card.getBoundingClientRect().top + window.scrollY - 6);
        } else {
            side.style.maxHeight = preview.style.maxHeight = '';
        }
    }
    document.getElementById('pp-dock').addEventListener('click', function (e) {
        var b = e.target.closest('[data-sheet]');
        if (b) sheet(wrap.getAttribute('data-sheet') === b.getAttribute('data-sheet') ? '' : b.getAttribute('data-sheet'));
    });
    wrap.addEventListener('click', function (e) {
        if (e.target.closest('[data-sheet-close]')) sheet('');
    });
    if (narrow.addEventListener) narrow.addEventListener('change', function () { sheet(''); render(); });

    function render() {
        renderLive();
        renderZone();
        document.getElementById('pp-dock-count').textContent = state.checklist.total;
        renderSymbols();
        renderToolbar();
        renderSide();
        renderPreview();
    }
    render();
})();
</script>
@endpush
