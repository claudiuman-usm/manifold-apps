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
                    <div class="pp-row" style="gap:6px 16px;">
                        <label class="pp-toggle"><input type="checkbox" id="pp-fov" checked>{{ __('podplan::messages.plan.fov') }}</label>
                        <label class="pp-toggle" title="{{ __('podplan::messages.plan.legend_axis') }}"><input type="checkbox" id="pp-axis-on">{{ __('podplan::messages.plan.axis') }}</label>
                    </div>
                </div>
                <div class="pp-canvas">
                    <svg id="pp-svg" viewBox="0 0 {{ $state['canvas']['w'] }} {{ $state['canvas']['h'] }}" role="img"
                         aria-label="{{ __('podplan::messages.plan.top_down') }}">
                    </svg>
                </div>
            </div>
            <div id="pp-planwarn" hidden></div>
            <div class="pp-toolbar" id="pp-toolbar"></div>
            @include('podplan::partials.plan-legend')
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
@include('podplan::partials.plan-canvas')
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
        axis: @json(route('podplan.plan.axis', $project)),
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
    var axisSel = false;
    var mode = (AI && /[?&]mode=describe/.test(location.search)) ? 'describe' : 'gear';
    var busy = false;
    var describeError = @json(session('pp_error'));
    var draft = @json((string) $project->description);
    var changeDraft = '';
    var query = '';
    // Whether the camera cones are drawn is a viewing preference, kept on this device.
    var fovOn = true;
    try { fovOn = localStorage.getItem('podplan.fov') !== '0'; } catch (err) {}

    // The drawing itself is shared with the secret-link page (partials/plan-canvas).
    var svg = document.getElementById('pp-svg');
    var canvas = window.ppCanvas(svg, {plan: T.plan, edit: true, view: function () {
        return {state: state, selected: selected, axisSel: axisSel, fovOn: fovOn};
    }});
    var narrow = canvas.narrow, LS = canvas.LS, size = canvas.size, axisOn = canvas.axisOn,
        renderZone = canvas.zone, renderSymbols = canvas.symbols, renderCables = canvas.cables, renderAxis = canvas.axis;
    function renderWarn() { canvas.warn(document.getElementById('pp-planwarn')); }
    var side = document.getElementById('pp-side');
    var preview = document.getElementById('pp-preview');
    var toolbar = document.getElementById('pp-toolbar');
    var modeBtn = document.getElementById('pp-mode');
    var W = state.canvas.w, H = state.canvas.h;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[c];
        });
    }
    function fmt(str, vars) {
        return String(str).replace(/:([a-z]+)/g, function (m, k) { return vars[k] != null ? vars[k] : m; });
    }
    function find(id) { return state.placements.find(function (p) { return p.id === id; }); }
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
            if (!axisOn()) axisSel = false;
            render();
            return s;
        });
    }

    // ------------------------------------------------------------------ axis

    function selectAxis(on) {
        axisSel = on;
        selected = null;
        renderSymbols();
        renderToolbar();
        if (mode === 'gear' && document.getElementById('pp-gear-list')) renderGearList();
    }

    function saveAxis() {
        var a = state.axis, r = function (v) { return Math.round(v * 10) / 10; };
        api('PATCH', URLS.axis, {on: true, x1: r(a.x1), y1: r(a.y1), x2: r(a.x2), y2: r(a.y2)});
    }

    // Turned about its middle. Near an edge it gets shorter rather than leave the canvas.
    function turnAxis(deg) {
        var a = state.axis, cx = (a.x1 + a.x2) / 2, cy = (a.y1 + a.y2) / 2;
        var half = Math.hypot(a.x2 - a.x1, a.y2 - a.y1) / 2;
        var ang = Math.atan2(a.y2 - a.y1, a.x2 - a.x1) + deg * Math.PI / 180;
        var ux = Math.cos(ang), uy = Math.sin(ang);
        if (Math.abs(ux) > 0.001) half = Math.min(half, Math.min(cx, W - cx) / Math.abs(ux));
        if (Math.abs(uy) > 0.001) half = Math.min(half, Math.min(cy, H - cy) / Math.abs(uy));
        a.x1 = cx - ux * half; a.y1 = cy - uy * half;
        a.x2 = cx + ux * half; a.y2 = cy + uy * half;
        saveAxis();
    }

    function toSvg(e) {
        var pt = svg.createSVGPoint();
        pt.x = e.clientX; pt.y = e.clientY;
        return pt.matrixTransform(svg.getScreenCTM().inverse());
    }

    var drag = null;
    svg.addEventListener('pointerdown', function (e) {
        var ax = e.target.closest('[data-axis]');
        if (ax && axisOn()) {
            var at = toSvg(e), a = state.axis;
            drag = {axis: ax.getAttribute('data-axis'), px: at.x, py: at.y, from: {x1: a.x1, y1: a.y1, x2: a.x2, y2: a.y2},
                    sx: e.clientX, sy: e.clientY, moved: false, pid: e.pointerId};
            // Captured on the canvas itself: the line is redrawn while it moves.
            try { svg.setPointerCapture(e.pointerId); } catch (err) {}
            e.preventDefault();
            return;
        }
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
        if (drag.axis) {
            var at = toSvg(e), f = drag.from, a = state.axis;
            if (drag.axis === 'move') {
                var mx = clamp(at.x - drag.px, -Math.min(f.x1, f.x2), W - Math.max(f.x1, f.x2));
                var my = clamp(at.y - drag.py, -Math.min(f.y1, f.y2), H - Math.max(f.y1, f.y2));
                a.x1 = f.x1 + mx; a.y1 = f.y1 + my; a.x2 = f.x2 + mx; a.y2 = f.y2 + my;
            } else {
                // An end follows the finger and the other end stays put, which turns the line.
                var nx = clamp(at.x, 0, W), ny = clamp(at.y, 0, H), far = drag.axis === 'a' ? [f.x2, f.y2] : [f.x1, f.y1];
                if (Math.hypot(nx - far[0], ny - far[1]) >= 60) {
                    if (drag.axis === 'a') { a.x1 = nx; a.y1 = ny; } else { a.x2 = nx; a.y2 = ny; }
                }
            }
            drag.moved = true;
            renderAxis();
            e.preventDefault();
            return;
        }
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
        var d = drag;
        drag = null;
        if (d.axis) {
            if (d.moved) { axisSel = true; selected = null; saveAxis(); }
            else selectAxis(!axisSel);
            return;
        }
        var p = find(d.id);
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
        axisSel = false;
        renderSymbols();
        renderToolbar();
        if (mode === 'gear' && document.getElementById('pp-gear-list')) renderGearList();
    }

    function turnIcon(d) {
        return '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + d + '</svg>';
    }
    var TURN_LEFT = '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/>', TURN_RIGHT = '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/>';

    function renderToolbar() {
        var p = selected ? find(selected) : null;
        if (axisSel && axisOn()) {
            toolbar.innerHTML = '<span class="pp-toolname">' + esc(T.plan.legend_axis) + '</span>' +
                '<span class="pp-muted pp-small">' + esc(T.plan.axis_hint) + '</span><span style="flex:1"></span>' +
                '<button type="button" class="pp-btn pp-btn--icon" data-axis-rot="-5" aria-label="' + esc(T.plan.rotate_left) + '" title="' + esc(T.plan.rotate_left) + '">' + turnIcon(TURN_LEFT) + '</button>' +
                '<button type="button" class="pp-btn pp-btn--icon" data-axis-rot="5" aria-label="' + esc(T.plan.rotate_right) + '" title="' + esc(T.plan.rotate_right) + '">' + turnIcon(TURN_RIGHT) + '</button>' +
                '<button type="button" class="pp-btn pp-btn--tight" data-axis-reset="1">' + esc(T.plan.axis_reset) + '</button>';
            return;
        }
        if (!p) {
            toolbar.innerHTML = '<span class="pp-muted pp-small">' + esc(T.plan.selected_none) + '</span>';
            return;
        }
        var h = '<span class="pp-toolname">' + esc(p.title) +
                (p.provider ? ' <span class="pp-tag pp-tag--rented">' + esc(fmt(T.plan.rented, {name: p.provider})) + '</span>' : '') + '</span>';
        if (p.role === 'camera') {
            // Numbers 1 to 8 at least (a mixer's inputs); taking one that is in use swaps the two cameras.
            var top = 8, cams = 0;
            state.placements.forEach(function (q) { if (q.role === 'camera') { cams++; if (q.number > top) top = q.number; } });
            h += '<label class="pp-toolask" for="pp-tool-num"><span>' + esc(T.plan.number) + '</span>' +
                 '<select class="pp-input" id="pp-tool-num" data-number="1" style="min-width:76px;"><option value="">' + esc(T.plan.no_number) + '</option>';
            for (var n = 1; n <= Math.max(top, cams); n++) h += '<option value="' + n + '"' + (p.number === n ? ' selected' : '') + '>' + n + '</option>';
            h += '</select></label>';
        }
        // Its lens is set right here, so there is no hunting for the camera in the checklist panel.
        var section = state.checklist.sections.find(function (s) { return s.item_id === p.item_id; });
        (section ? section.prompts : []).forEach(function (q) {
            var id = 'pp-tool-ask-' + q.placement_id + '-' + q.question.replace(/[^a-z0-9]+/gi, '-');
            h += '<label class="pp-toolask" for="' + id + '"><span>' + esc(q.question) + '</span>' + askSelect(q, id) + '</label>';
        });
        h += '<span style="flex:1"></span>';
        if (p.role === 'camera' || p.role === 'light') {
            h += '<button type="button" class="pp-btn pp-btn--icon" data-rot="-15" aria-label="' + esc(T.plan.rotate_left) + '" title="' + esc(T.plan.rotate_left) + '">' +
                 turnIcon(TURN_LEFT) + '</button>' +
                 '<button type="button" class="pp-btn pp-btn--icon" data-rot="15" aria-label="' + esc(T.plan.rotate_right) + '" title="' + esc(T.plan.rotate_right) + '">' +
                 turnIcon(TURN_RIGHT) + '</button>' +
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
    toolbar.addEventListener('change', function (e) {
        var num = e.target.closest('select[data-number]');
        if (num && selected) { api('PATCH', URLS.placements + '/' + selected, {number: num.value === '' ? null : +num.value}); return; }
        answerFrom(e);
    });
    toolbar.addEventListener('click', function (e) {
        var turn = e.target.closest('[data-axis-rot]');
        if (turn && axisOn()) { turnAxis(+turn.getAttribute('data-axis-rot')); return; }
        if (e.target.closest('[data-axis-reset]')) { api('PATCH', URLS.axis, {on: true, reset: true}); return; }
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

    // The axis belongs to the plan, so showing it is saved with the project.
    var axisBox = document.getElementById('pp-axis-on');
    axisBox.addEventListener('change', function () {
        axisSel = axisBox.checked;
        if (axisSel) selected = null;
        api('PATCH', URLS.axis, {on: axisBox.checked});
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
        axisBox.checked = axisOn();
        renderSymbols();
        renderWarn();
        renderToolbar();
        renderSide();
        renderPreview();
    }
    render();
})();
</script>
@endpush
