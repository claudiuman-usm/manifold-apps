<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>{{ $project->name }} · {{ __('podplan::messages.checklist.heading') }}</title>
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

        @include('podplan::partials.checklist-app', [
            'owner' => false,
            'payload' => $payload,
            'urls' => [
                'state' => route('podplan.share.state', $token),
                'tick' => route('podplan.share.tick', $token),
            ],
        ])

        @php($ppVersion = app(\App\Modules\ModuleRegistry::class)->get('podplan')?->manifest['version'] ?? null)
        @if ($ppVersion)
            <p class="pp-muted" style="margin-top:28px;text-align:center;font-size:.75rem;">{{ __('podplan::messages.title') }} {{ __('podplan::messages.version', ['version' => $ppVersion]) }}</p>
        @endif
    </div>
</main>
</body>
</html>
