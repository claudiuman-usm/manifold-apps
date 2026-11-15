{{--
    The packing list, shared by the owner's Checklist screen and the secret-link
    page. $owner switches on everything that changes the list itself (adding
    items, resetting, sharing); a guest can only tick. Nobody chooses a lens
    here: this screen is for packing, choices are made on the Plan.
--}}
<div class="pp-card pp-hero">
    <div class="pp-row" style="align-items:flex-end;gap:12px 18px;">
        <div class="pp-row" style="flex:1 1 220px;align-items:baseline;gap:10px;">
            <span class="pp-big" id="pp-cl-num">0</span>
            <span class="pp-muted" id="pp-cl-of"></span>
        </div>
        <div class="pp-seg" id="pp-cl-sort" role="group" aria-label="{{ __('podplan::messages.checklist.sort_kind') }}">
            <button type="button" data-sort="gear">{{ __('podplan::messages.checklist.sort_gear') }}</button>
            <button type="button" data-sort="kind">{{ __('podplan::messages.checklist.sort_kind') }}</button>
        </div>
        <div class="pp-seg" id="pp-cl-filter" role="group" aria-label="{{ __('podplan::messages.checklist.heading') }}">
            <button type="button" data-filter="all">{{ __('podplan::messages.checklist.filter_all') }}</button>
            <button type="button" data-filter="todo">{{ __('podplan::messages.checklist.filter_todo') }}</button>
            <button type="button" data-filter="rented">{{ __('podplan::messages.checklist.filter_rented') }}</button>
        </div>
    </div>
    <div class="pp-progress"><span id="pp-cl-bar"></span></div>
</div>

<div class="pp-cl">
    <div class="pp-cl-main">
        <div class="pp-stack" id="pp-cl-notes" style="gap:10px;"></div>
        <div class="pp-cl-grid" id="pp-cl-list"></div>
    </div>

    @if ($owner)
        <div class="pp-cl-side">
            <form class="pp-card pp-stack" id="pp-cl-add" style="gap:8px;">
                <label class="pp-label" for="pp-cl-add-input">{{ __('podplan::messages.checklist.add_item') }}</label>
                <div class="pp-addrow">
                    <input class="pp-input pp-input--pill" id="pp-cl-add-input" maxlength="160" placeholder="{{ __('podplan::messages.checklist.add_placeholder') }}">
                    <button class="pp-btn" type="submit">{{ __('podplan::messages.checklist.add') }}</button>
                </div>
            </form>

            <div class="pp-card pp-stack" style="gap:8px;">
                <h2 style="font-size:.97rem;font-weight:600;">{{ __('podplan::messages.checklist.share') }}</h2>
                <p class="pp-help">{{ __('podplan::messages.checklist.share_help') }}</p>
                <div class="pp-stack" id="pp-cl-share" style="gap:8px;"></div>
            </div>

            <button type="button" class="pp-btn pp-btn--quiet" id="pp-cl-reset" style="align-self:flex-start;">{{ __('podplan::messages.checklist.reset') }}</button>
        </div>
    @endif
</div>

<script>
(function () {
    var OWNER = @json($owner);
    var T = {
        c: @json(__('podplan::messages.checklist')),
        plan: @json(__('podplan::messages.plan')),
        tags: @json(__('podplan::messages.tags')),
        openOne: @json(trans_choice('podplan::messages.checklist.open', 1)),
        openMany: @json(trans_choice('podplan::messages.checklist.open', 5, ['count' => ':count']))
    };
    var URLS = @json($urls);
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;
    var payload = @json($payload);
    var filter = 'all', pending = 0, resetArmed = false;
    // By gear (what goes with each camera) or by kind (all tripods together): a
    // packing preference, remembered on this device.
    var sort = 'gear';
    try { if (localStorage.getItem('podplan.sort') === 'kind') sort = 'kind'; } catch (err) {}

    var list = document.getElementById('pp-cl-list');
    var notes = document.getElementById('pp-cl-notes');

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[c];
        });
    }
    function fmt(str, vars) {
        return String(str).replace(/:([a-z]+)/g, function (m, k) { return vars[k] != null ? vars[k] : m; });
    }

    function request(method, url, body) {
        pending++;
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest'},
            body: body ? JSON.stringify(body) : undefined
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).finally(function () { pending--; });
    }
    function api(method, url, body) {
        return request(method, url, body).then(function (p) { payload = p; render(); })
            .catch(function () { refresh(); });
    }
    function refresh() {
        return request('GET', URLS.state).then(function (p) {
            if (JSON.stringify(p) !== JSON.stringify(payload)) { payload = p; render(); }
        }).catch(function () {});
    }

    function tags(r) {
        var h = '';
        if (r.live) h += '<span class="pp-tag pp-tag--live">' + esc(T.tags.live) + '</span>';
        if (r.rule) h += '<span class="pp-tag pp-tag--rule">' + esc(T.tags.rule) + '</span>';
        if (r.rented) h += '<span class="pp-tag pp-tag--rented">' + esc(fmt(T.plan.rented, {name: r.rented})) + '</span>';
        if (r.short) h += '<span class="pp-tag pp-tag--short">' + esc(T.tags.short) + '</span>';
        return h;
    }
    function row(key, label, packed, extra, sub, rented) {
        if (filter === 'todo' && packed) return '';
        if (filter === 'rented' && !rented) return '';
        return '<label class="pp-check' + (packed ? ' is-packed' : '') + '"><input type="checkbox" data-key="' + esc(key) + '"' + (packed ? ' checked' : '') + '>' +
               '<span class="lb">' + esc(label) + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</span><span class="tags">' + (extra || '') + '</span></label>';
    }
    function card(title, meta, body) {
        return '<div class="pp-card"><div class="pp-cardhead"><h2>' + esc(title) + '</h2>' + (meta || '') + '</div>' + body + '</div>';
    }
    // "3 of 7" beside a card's name: how far that card is packed.
    function tally(rows) {
        var done = rows.filter(function (r) { return r.packed; }).length;
        return '<span class="pp-muted pp-small">' + done + ' / ' + rows.length + '</span>';
    }

    // Every generated row regrouped by what it is: one card per accessory kind
    // (tripods, light stands, lenses...) and one for everything that is not a
    // counted accessory. Each row says which gear it is for.
    function byKind(c) {
        var groups = {}, OTHER = '\uffff';
        function put(r, owner) {
            var name = r.kind || OTHER;
            if (!groups[name]) groups[name] = {name: name, sort: r.kind ? r.kind_sort : 1e9, rows: []};
            groups[name].rows.push({r: r, owner: owner});
        }
        c.sections.forEach(function (s) { s.rows.forEach(function (r) { put(r, s.title); }); });
        c.general.forEach(function (r) { put(r, null); });

        return Object.keys(groups).map(function (k) { return groups[k]; })
            .sort(function (a, b) { return a.sort - b.sort; })
            .map(function (g) {
                // Loose items are sorted by name so all the AC adapters sit together.
                if (g.name === OTHER) g.rows.sort(function (a, b) { return a.r.label.localeCompare(b.r.label); });
                var body = g.rows.map(function (x) {
                    return row(x.r.key, x.r.label, x.r.packed, tags(x.r), x.owner && !x.r.self ? fmt(T.c.for, {name: x.owner}) : '', x.r.rented);
                }).join('');
                return body ? card(g.name === OTHER ? T.c.other : g.name, tally(g.rows.map(function (x) { return x.r; })), body) : '';
            }).join('');
    }

    function render() {
        var c = payload.checklist, p = payload.project, h = '';

        document.getElementById('pp-cl-num').textContent = c.packed;
        document.getElementById('pp-cl-of').textContent = !c.total ? T.c.empty
            : (c.packed === c.total ? T.c.all_packed : fmt(T.c.of_packed, {total: c.total}));
        document.getElementById('pp-cl-bar').style.width = (c.total ? Math.round(c.packed / c.total * 100) : 0) + '%';
        document.querySelectorAll('#pp-cl-filter button').forEach(function (b) {
            var on = b.getAttribute('data-filter') === filter;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on);
        });

        document.querySelectorAll('#pp-cl-sort button').forEach(function (b) {
            var on = b.getAttribute('data-sort') === sort;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on);
        });

        // Choices that are still open sit at the top: they are not items yet,
        // and they are made on the Plan, not here.
        var open = [];
        c.sections.forEach(function (s) {
            s.prompts.forEach(function (q) { if (!q.chosen) open.push({s: s, q: q}); });
        });
        if (open.length) {
            h += '<div class="pp-notice"><strong>' + esc(open.length === 1 ? T.openOne : fmt(T.openMany, {count: open.length})) + '</strong>';
            open.forEach(function (o) {
                h += '<span class="pp-small">' + esc(fmt(T.c.still_open, {question: o.q.question, name: o.s.title})) + '</span>';
            });
            if (OWNER) {
                h += '<span class="pp-small">' + esc(T.c.open_help) + ' <a href="' + URLS.plan + '">' + esc(T.c.choose_in_plan) + '</a></span>';
            }
            h += '</div>';
        }

        if (OWNER) {
            c.warnings.forEach(function (w) {
                if (w.type === 'need_mixer') h += '<div class="pp-notice pp-notice--live">' + esc(T.plan.need_mixer) + '</div>';
                else if (w.type === 'short') h += '<div class="pp-notice pp-notice--bad">' + esc(fmt(T.plan.short, {label: w.label, count: w.count})) + '</div>';
                else if (w.type === 'double') h += '<div class="pp-notice pp-notice--live">' + esc(fmt(T.plan.double, {label: w.label, project: w.project})) + '</div>';
            });
        }

        notes.innerHTML = h;
        h = '';

        var shown = 0;
        if (sort === 'kind') {
            var grouped = byKind(c);
            if (grouped) { shown++; h += grouped; }
        } else {
            c.sections.forEach(function (s) {
                // In its own card the gear itself reads as what it is ("Camera"), not its name again.
                var rows = s.rows.map(function (r) { return row(r.key, r.self ? r.kind : r.label, r.packed, tags(r), '', r.rented); }).join('');
                if (!rows) return;
                shown++;
                h += card(s.title, tally(s.rows) +
                    (s.provider ? '<span class="pp-tag pp-tag--rented">' + esc(fmt(T.plan.rented, {name: s.provider})) + '</span>' : ''), rows);
            });

            var general = c.general.map(function (r) { return row(r.key, r.label, r.packed, tags(r), '', r.rented); }).join('');
            if (general) { shown++; h += card(T.c.general, tally(c.general), general); }
        }

        var manual = c.manual.map(function (m) {
            var extra = '<span class="pp-tag">' + esc(T.tags.manual) + '</span>' +
                (OWNER ? '<button type="button" class="pp-btn pp-btn--danger pp-btn--tight" data-unmanual="' + m.id + '">' + esc(T.c.remove) + '</button>' : '');
            return row('m:' + m.id, m.label, m.packed, extra);
        }).join('');
        if (manual) { shown++; h += card(T.c.manual, tally(c.manual), manual); }

        if (!shown) {
            h += '<div class="pp-card pp-muted" style="padding-bottom:14px;">' + esc(!c.total ? T.c.empty : filter === 'rented' ? T.c.nothing_rented : T.c.nothing_left) + '</div>';
        }
        list.innerHTML = h;
        if (shown) masonry();

        if (OWNER) renderOwner();
    }

    /*
     * Masonry: cards are as tall as their lists, so instead of rows with gaps
     * under the short ones they are stacked in columns. The order is kept: the
     * first cards go across the top, and each next one drops into whichever
     * column is shortest so far.
     */
    var COL = 290, GAP = 14, colCount = 0, laidOut = false;
    function columnsThatFit() { return Math.max(1, Math.floor((list.clientWidth + GAP) / (COL + GAP))); }
    function masonry() {
        // While the list is not on screen (the Plan tab of the shared page) nothing
        // can be measured: it is laid out when it comes back.
        laidOut = !!list.clientWidth;
        if (!laidOut) return;
        var cards = Array.prototype.slice.call(list.querySelectorAll('.pp-card'));
        var cols = [];
        colCount = columnsThatFit();
        list.innerHTML = '';
        for (var i = 0; i < colCount; i++) {
            cols.push(list.appendChild(document.createElement('div')));
            cols[i].className = 'pp-cl-col';
        }
        cards.forEach(function (card) {
            var best = cols[0];
            cols.forEach(function (c) { if (c.offsetHeight < best.offsetHeight) best = c; });
            best.appendChild(card);
        });
    }
    // A wider or narrower page, or the list coming back on screen, changes how many columns fit.
    function refit() {
        if (!list.clientWidth) return;
        if (!laidOut ? list.querySelector('.pp-card:not(.pp-muted)') : columnsThatFit() !== colCount) masonry();
    }
    if (window.ResizeObserver) new ResizeObserver(refit).observe(list);
    else window.addEventListener('resize', refit);

    function renderOwner() {
        var box = document.getElementById('pp-cl-share'), url = payload.share_url, h;
        if (url) {
            h = '<input class="pp-input pp-input--pill" id="pp-cl-url" readonly value="' + esc(url) + '" aria-label="' + esc(T.c.share) + '" onclick="this.select()">' +
                '<div class="pp-row"><button type="button" class="pp-btn pp-btn--primary pp-btn--tight" data-share="copy">' + esc(T.c.share_copy) + '</button>' +
                '<button type="button" class="pp-btn pp-btn--tight" data-share="create">' + esc(T.c.share_replace) + '</button>' +
                '<button type="button" class="pp-btn pp-btn--danger pp-btn--tight" data-share="stop">' + esc(T.c.share_stop) + '</button></div>';
        } else {
            h = '<button type="button" class="pp-btn pp-btn--primary" data-share="create" style="align-self:flex-start;">' + esc(T.c.share_create) + '</button>';
        }
        box.innerHTML = h;
        var reset = document.getElementById('pp-cl-reset');
        reset.textContent = resetArmed ? T.c.reset_confirm : T.c.reset;
        reset.classList.toggle('pp-btn--danger', resetArmed);
        reset.classList.toggle('pp-btn--quiet', !resetArmed);
    }

    list.addEventListener('change', function (e) {
        var box = e.target.closest('input[data-key]');
        if (!box) return;
        box.closest('.pp-check').classList.toggle('is-packed', box.checked);
        api('POST', URLS.tick, {key: box.getAttribute('data-key'), packed: box.checked});
    });

    list.addEventListener('click', function (e) {
        var rm = e.target.closest('[data-unmanual]');
        if (rm) { e.preventDefault(); api('DELETE', URLS.manual + '/' + rm.getAttribute('data-unmanual')); }
    });

    document.getElementById('pp-cl-sort').addEventListener('click', function (e) {
        var b = e.target.closest('[data-sort]');
        if (!b) return;
        sort = b.getAttribute('data-sort');
        try { localStorage.setItem('podplan.sort', sort); } catch (err) {}
        render();
    });

    document.getElementById('pp-cl-filter').addEventListener('click', function (e) {
        var b = e.target.closest('[data-filter]');
        if (b) { filter = b.getAttribute('data-filter'); render(); }
    });

    if (OWNER) {
        document.getElementById('pp-cl-add').addEventListener('submit', function (e) {
            e.preventDefault();
            var input = document.getElementById('pp-cl-add-input'), label = input.value.trim();
            if (!label) return;
            input.value = '';
            api('POST', URLS.manual, {label: label});
        });
        document.getElementById('pp-cl-reset').addEventListener('click', function () {
            if (!resetArmed) {
                resetArmed = true; renderOwner();
                setTimeout(function () { resetArmed = false; renderOwner(); }, 4000);
                return;
            }
            resetArmed = false;
            api('POST', URLS.reset);
        });
        document.getElementById('pp-cl-share').addEventListener('click', function (e) {
            var b = e.target.closest('[data-share]');
            if (!b) return;
            var act = b.getAttribute('data-share');
            if (act === 'create') api('POST', URLS.share);
            else if (act === 'stop') api('DELETE', URLS.share);
            else if (act === 'copy') {
                var input = document.getElementById('pp-cl-url');
                var done = function () { b.textContent = T.c.share_copied; setTimeout(function () { b.textContent = T.c.share_copy; }, 1600); };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(input.value).then(done).catch(function () { input.select(); });
                } else { input.select(); document.execCommand && document.execCommand('copy'); done(); }
            }
        });
    }

    render();

    // Everyone packing sees everyone's ticks: poll while the page is visible.
    setInterval(function () { if (!document.hidden && pending === 0) refresh(); }, 5000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
})();
</script>
