<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>{{ $project->name }} · {{ __('podplan::messages.title') }}</title>
    <script>
        // No theme switch here; follow the phone's setting.
        if (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    {{-- The hub's stylesheet is public (the login page uses it), so the shared page wears the same look. --}}
    <link rel="stylesheet" href="{{ route('assets.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @include('podplan::partials.styles')
</head>
<body class="ctx-podplan">
<div class="bg-decor" aria-hidden="true">
    <span class="orb orb-1"></span>
    <span class="orb orb-2"></span>
    <span class="orb orb-3"></span>
</div>
<main class="content" style="max-width: 980px;">
    <div class="pp">
        <p class="pp-eyebrow" style="margin-bottom:6px;">{{ __('podplan::messages.checklist.shared_note') }}</p>
        <div class="pp-top">
            <div class="pp-grow">
                <h1>{{ $project->name }}</h1>
                <p class="pp-muted">
                    {{ __('podplan::messages.shoot_types.'.$project->shoot_type) }}
                    @if ($project->shoot_date) · {{ $project->shoot_date->translatedFormat('j M Y') }} @endif
                    @if ($project->location) · {{ $project->location }} @endif
                </p>
            </div>
        </div>

        <div class="pp-seg" id="pp-share-tabs" role="tablist" style="align-self:flex-start;margin-bottom:14px;">
            <button type="button" role="tab" data-tab="checklist" class="is-on" aria-selected="true">{{ __('podplan::messages.nav.checklist') }}</button>
            <button type="button" role="tab" data-tab="plan" aria-selected="false">{{ __('podplan::messages.nav.plan') }}</button>
        </div>

        <div id="pp-tab-checklist" role="tabpanel">
            @include('podplan::partials.checklist-app', [
                'owner' => false,
                'payload' => $payload,
                'urls' => [
                    'state' => route('podplan.share.state', $token),
                    'tick' => route('podplan.share.tick', $token),
                ],
            ])
        </div>

        {{-- The plan, to look at: the same drawing as the owner's Plan screen, with nothing to grab. --}}
        <div id="pp-tab-plan" role="tabpanel" class="pp-stage pp-shareplan" hidden>
            <div class="pp-card pp-stagecard">
                <div class="pp-row pp-between">
                    <h2>{{ __('podplan::messages.plan.top_down') }}</h2>
                    <label class="pp-toggle"><input type="checkbox" id="pp-fov" checked>{{ __('podplan::messages.plan.fov') }}</label>
                </div>
                <div class="pp-canvas">
                    <svg id="pp-svg" viewBox="0 0 {{ $plan['canvas']['w'] }} {{ $plan['canvas']['h'] }}" role="img"
                         aria-label="{{ __('podplan::messages.plan.top_down') }}"></svg>
                </div>
            </div>
            <div id="pp-planwarn" hidden></div>
            @include('podplan::partials.plan-legend')
        </div>

        @include('podplan::partials.plan-canvas')
        <script>
        (function () {
            var plan = @json($plan);
            var URL = @json(route('podplan.share.plan', $token));
            var fovOn = true;
            try { fovOn = localStorage.getItem('podplan.fov') !== '0'; } catch (err) {}

            var canvas = window.ppCanvas(document.getElementById('pp-svg'), {
                plan: @json(__('podplan::messages.plan')), edit: false,
                view: function () { return {state: plan, selected: null, axisSel: false, fovOn: fovOn}; }
            });
            function draw() {
                canvas.zone();
                canvas.symbols();
                canvas.warn(document.getElementById('pp-planwarn'));
            }

            var tabs = document.getElementById('pp-share-tabs');
            var panels = {checklist: document.getElementById('pp-tab-checklist'), plan: document.getElementById('pp-tab-plan')};
            var tab = 'checklist';
            function show(name) {
                tab = panels[name] ? name : 'checklist';
                Object.keys(panels).forEach(function (k) { panels[k].hidden = k !== tab; });
                tabs.querySelectorAll('button').forEach(function (b) {
                    var on = b.getAttribute('data-tab') === tab;
                    b.classList.toggle('is-on', on);
                    b.setAttribute('aria-selected', on);
                });
                // The link can open straight on the plan: add #plan to it.
                try { history.replaceState(null, '', tab === 'plan' ? '#plan' : location.pathname + location.search); } catch (err) {}
                if (tab === 'plan') { draw(); refresh(); }
            }
            tabs.addEventListener('click', function (e) {
                var b = e.target.closest('[data-tab]');
                if (b) show(b.getAttribute('data-tab'));
            });

            // The plan follows the owner's changes while it is on screen.
            function refresh() {
                fetch(URL, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
                    .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                    .then(function (p) { if (JSON.stringify(p) !== JSON.stringify(plan)) { plan = p; draw(); } })
                    .catch(function () {});
            }
            setInterval(function () { if (!document.hidden && tab === 'plan') refresh(); }, 5000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden && tab === 'plan') refresh(); });

            var fovBox = document.getElementById('pp-fov');
            fovBox.checked = fovOn;
            fovBox.addEventListener('change', function () {
                fovOn = fovBox.checked;
                try { localStorage.setItem('podplan.fov', fovOn ? '1' : '0'); } catch (err) {}
                draw();
            });
            if (canvas.narrow.addEventListener) canvas.narrow.addEventListener('change', function () { if (tab === 'plan') draw(); });

            show(location.hash === '#plan' ? 'plan' : 'checklist');
        })();
        </script>

        @php($ppVersion = app(\App\Modules\ModuleRegistry::class)->get('podplan')?->manifest['version'] ?? null)
        @if ($ppVersion)
            <p class="pp-muted" style="margin-top:28px;text-align:center;font-size:.75rem;">{{ __('podplan::messages.title') }} {{ __('podplan::messages.version', ['version' => $ppVersion]) }}</p>
        @endif
    </div>
</main>
</body>
</html>
