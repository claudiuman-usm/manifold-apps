{{--
    Draws a plan into an <svg>. Shared by the Plan screen, where the gear can
    be dragged, and the secret-link page, where it is only looked at, so the
    two never drift apart.

    ppCanvas(svg, {plan: messages, edit: bool, view: function () {
        return {state, selected, axisSel, fovOn};
    }}) returns the drawing functions. Every call reads the view afresh.
--}}
<script>
window.ppCanvas = function (svg, opts) {
    var T = {plan: opts.plan};
    var EDIT = !!opts.edit;
    var state, selected, axisSel, fovOn, W, H, SIZES;

    // Back to front: FOH corner, cables, the axis's grab area, the gear, the axis line itself.
    svg.innerHTML = '<g></g><g></g><g></g><g></g><g></g>';
    var gZone = svg.children[0], gCables = svg.children[1], gAxisGrab = svg.children[2],
        gSymbols = svg.children[3], gAxis = svg.children[4];

    // On a phone the whole diagram is fitted to the width (about half size), so
    // labels are drawn larger and tap areas wider to stay readable and grabbable.
    var narrow = window.matchMedia ? window.matchMedia('(max-width: 760px)') : {matches: false};
    function LS() { return narrow.matches ? 1.7 : 1; }

    function sync() {
        var v = opts.view();
        state = v.state; selected = v.selected; axisSel = v.axisSel; fovOn = v.fovOn;
        W = state.canvas.w; H = state.canvas.h; SIZES = state.canvas.sizes;
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }
    function fmt(str, vars) {
        return String(str).replace(/:([a-z]+)/g, function (m, k) { return vars[k] != null ? vars[k] : m; });
    }
    function find(id) { return state.placements.find(function (p) { return p.id === id; }); }
    function size(p) { return SIZES[p.symbol] || SIZES.box; }
    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

        // ---------------------------------------------------------------- canvas

        // The little "radio waves" mark for a wireless video link: on the camera that
        // sends and on the mixer that receives. Drawn upright whatever way the gear is turned.
        function waves(cx, cy, color) {
            var st = 'fill:none;stroke:' + color + ';stroke-width:1.8;stroke-linecap:round';
            return '<g pointer-events="none"><circle cx="' + cx + '" cy="' + (cy + 4.2) + '" r="1.7" style="fill:' + color + '"/>' +
                '<path d="M' + (cx - 4) + ' ' + (cy + 1.2) + ' A5.6 5.6 0 0 1 ' + (cx + 4) + ' ' + (cy + 1.2) + '" style="' + st + '"/>' +
                '<path d="M' + (cx - 7.4) + ' ' + (cy - 2.2) + ' A10.4 10.4 0 0 1 ' + (cx + 7.4) + ' ' + (cy - 2.2) + '" style="' + st + '"/></g>';
        }

        // With a number on the camera body the wireless mark moves to its corner, like a notification dot.
        function badge(cx, cy) {
            var k = narrow.matches ? 1.3 : 1;
            return '<g pointer-events="none"><circle cx="' + cx + '" cy="' + cy + '" r="' + (8.5 * k) + '" style="fill:var(--pp-surface);stroke:var(--pp-accent);stroke-width:1.5"/>' +
                '<g transform="translate(' + cx + ',' + (cy - 0.4 * k) + ') scale(' + (0.62 * k) + ')">' + waves(0, 0, 'var(--pp-accent-ink)') + '</g></g>';
        }

        function symbol(p) {
            var rot = p.rot || 0;
            switch (p.symbol) {
                case 'cam':
                    // The cone opens to the lens's angle of view: a long lens draws a narrow one.
                    var half = 118 * Math.tan(((p.fov || 52) / 2) * Math.PI / 180);
                    return '<g transform="rotate(' + rot + ' 20 14)">' +
                        (fovOn ? '<polygon points="20,2 ' + (20 - half).toFixed(1) + ',-116 ' + (20 + half).toFixed(1) + ',-116" pointer-events="none" style="fill:var(--pp-accent);fill-opacity:.14"/>' : '') +
                        // A camera that crossed the axis of action is ringed in red.
                        (p.across ? '<rect x="-5" y="-10" width="50" height="43" rx="10" pointer-events="none" style="fill:none;stroke:var(--pp-bad);stroke-width:2.5"/>' : '') +
                        '<rect width="40" height="28" rx="6" style="fill:var(--pp-accent)"/>' +
                        '<rect x="14" y="-6" width="12" height="8" rx="2" style="fill:var(--pp-accent-ink)"/></g>' +
                        (p.number != null ? '<text class="num" x="20" y="' + (14 + numSize() * 0.36) + '" text-anchor="middle" style="font-size:' + numSize() + 'px">' + p.number + '</text>' : '') +
                        (p.wireless ? (p.number != null ? badge(39, 0) : waves(20, 13, 'var(--accent-ink)')) : '');
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

        function numSize() { return narrow.matches ? 19 : 15; }

        function renderSymbols() {
            var rank = {furniture: 0, person: 1, gear: 2};
            var list = state.placements.slice().sort(function (a, b) { return rank[a.kind] - rank[b.kind]; });
            var h = '';
            var k = LS(), hit = narrow.matches ? 82 : 44;
            var f = 'style="font-size:' + (12 * k) + 'px"', fs = 'style="font-size:' + (11.5 * k) + 'px"';
            list.forEach(function (p) {
                var s = size(p), w = s[0], ht = s[1];
                var above = p.symbol === 'mic' || p.symbol === 'wireless';
                h += '<g' + (EDIT ? ' data-id="' + p.id + '"' : '') + ' transform="translate(' + p.x + ',' + p.y + ')">';
                // A generous invisible hit area, so small symbols are easy to grab on a phone.
                if (EDIT) h += '<rect x="' + Math.min(-8, (w - hit) / 2) + '" y="' + Math.min(-8, (ht - hit) / 2) + '" width="' + Math.max(w + 16, hit) +
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
            renderAxis();
        }

        // ------------------------------------------------------------------ axis

        function axisOn() { return state && !!(state.axis && state.axis.on && state.axis.x1 != null); }

        // The rule holds for the whole room, so the line is carried on, faintly, to the edges of the canvas.
        function axisReach(a) {
            var dx = a.x2 - a.x1, dy = a.y2 - a.y1, lo = -1e6, hi = 1e6;
            [[a.x1, dx, W], [a.y1, dy, H]].forEach(function (c) {
                if (Math.abs(c[1]) < 0.001) return;
                var t1 = -c[0] / c[1], t2 = (c[2] - c[0]) / c[1];
                lo = Math.max(lo, Math.min(t1, t2));
                hi = Math.min(hi, Math.max(t1, t2));
            });
            return [a.x1 + dx * lo, a.y1 + dy * lo, a.x1 + dx * hi, a.y1 + dy * hi];
        }

        /*
         * The line itself is drawn over everything, but it is grabbed from under
         * the gear: people and microphones sit right on it and must stay easy to
         * pick up. Its two end handles are on top, clear of the set.
         */
        function renderAxis() {
            if (!axisOn()) { gAxis.innerHTML = gAxisGrab.innerHTML = ''; return; }
            var a = state.axis, r = axisReach(a), k = LS(), big = narrow.matches;
            var line = function (p, attrs) {
                return '<line x1="' + p[0].toFixed(1) + '" y1="' + p[1].toFixed(1) + '" x2="' + p[2].toFixed(1) + '" y2="' + p[3].toFixed(1) + '" ' + attrs + '/>';
            };
            var seg = [a.x1, a.y1, a.x2, a.y2];
            gAxisGrab.innerHTML = !EDIT ? '' : line(seg, 'data-axis="move" pointer-events="stroke" style="stroke:transparent;stroke-width:' + (big ? 46 : 24) + '"');

            var h = line(r, 'pointer-events="none" style="stroke:var(--pp-axis);stroke-opacity:.4;stroke-width:1.5;stroke-dasharray:2 6"') +
                    line(seg, 'pointer-events="none" style="stroke:var(--pp-axis);stroke-width:' + (axisSel ? 3.6 : 2.6) + ';stroke-dasharray:14 5 2 5;stroke-linecap:round"');
            (EDIT ? [['a', a.x1, a.y1], ['b', a.x2, a.y2]] : []).forEach(function (e) {
                h += '<g data-axis="' + e[0] + '"><circle cx="' + e[1] + '" cy="' + e[2] + '" r="' + (big ? 26 : 15) + '" fill="transparent"/>' +
                     '<circle cx="' + e[1] + '" cy="' + e[2] + '" r="' + (big ? 9 : 6.5) + '" style="fill:var(--pp-surface);stroke:var(--pp-axis);stroke-width:' + (axisSel ? 3.4 : 2.6) + '"/></g>';
            });
            // Named at whichever end sits higher, where there is usually room.
            var top = a.y1 <= a.y2 ? [a.x1, a.y1] : [a.x2, a.y2];
            h += '<text class="axis" style="font-size:' + (10.5 * k) + 'px" text-anchor="middle" x="' + clamp(top[0], 24 * k, W - 24 * k) + '" y="' +
                 Math.max(top[1] - 12 * k, 11 * k) + '">180°</text>';
            gAxis.innerHTML = h;
        }

        function renderWarn(box) {
            var list = state.plan_warnings || [];
            box.hidden = !list.length;
            box.innerHTML = list.map(function (w) {
                var name = w.number != null ? T.plan.cam + ' ' + w.number : w.label;
                return '<div class="pp-notice pp-notice--bad">' + esc(fmt(T.plan.axis_cross, {name: name})) + '</div>';
            }).join('');
        }

        // FOH, the mixer corner: where mixers land by default and where every cable ends up.
        function renderZone() {
            var z = state.canvas.zone;
            if (!z) return;
            gZone.innerHTML =
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

    function synced(fn) { return function (a) { sync(); return fn(a); }; }

    return {
        narrow: narrow,
        LS: LS,
        size: synced(size),
        axisOn: synced(axisOn),
        zone: synced(renderZone),
        symbols: synced(renderSymbols),   // the gear, and with it the cables and the axis
        cables: synced(renderCables),
        axis: synced(renderAxis),
        warn: synced(renderWarn)
    };
};
</script>
