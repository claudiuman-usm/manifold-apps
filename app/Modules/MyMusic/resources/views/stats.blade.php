@extends('layouts.app')
@section('title', __('music::messages.stats.heading'))
@section('bodyClass', 'ctx-music')

@php
    $palette = ['#db2777', '#f472b6', '#c084fc', '#818cf8', '#38bdf8', '#34d399', '#fbbf24', '#fb7185', '#a3a3a3'];
    $maxArtist = $topArtists->max('plays') ?: 1;
    $maxGenre = $genres->max('plays') ?: 1;
    $maxDecade = $decades->max() ?: 1;
@endphp

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('music.index') }}">{{ __('music::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ __('music::messages.stats.heading') }}</span>
    </div>

    <div class="row-between page-head">
        <div>
            <h1>{{ __('music::messages.stats.heading') }}</h1>
            <p>{{ __('music::messages.stats.subheading') }}</p>
        </div>
        <a href="{{ route('music.index') }}" class="btn btn-ghost">{{ __('music::messages.stats.back') }}</a>
    </div>

    {{-- Totals --}}
    <div class="card card-pad" style="margin-bottom:20px;">
        <div class="stats-totals">
            <div>
                <div class="stat-label">{{ __('music::messages.stats.total_plays') }}</div>
                <div class="stat-num">{{ number_format($totals['plays']) }}</div>
                <div class="muted" style="font-size:.82rem;">{{ $totals['plays_this_month'] }} {{ __('music::messages.stats.this_month') }}</div>
            </div>
            <div>
                <div class="stat-label">{{ __('music::messages.stats.tracks_played') }}</div>
                <div class="stat-num">{{ number_format($totals['played']) }}<span class="stat-sub"> / {{ number_format($totals['tracks']) }}</span></div>
            </div>
            <div>
                <div class="stat-label">{{ __('music::messages.stats.tracks_rated') }}</div>
                <div class="stat-num">{{ number_format($totals['rated']) }}</div>
            </div>
        </div>
    </div>

    @if ($totals['plays'] === 0 && $totals['rated'] === 0)
        <div class="empty-state card card-pad" style="margin-bottom:20px;">{{ __('music::messages.stats.empty') }}</div>
    @endif

    {{-- AI taste analysis --}}
    @if ($aiConfigured)
        <div class="card card-pad" style="margin-bottom:20px;">
            <div class="row-between" style="align-items:flex-start;gap:14px;flex-wrap:wrap;">
                <div style="max-width:560px;">
                    <h2 style="margin:0 0 4px;">{{ __('music::messages.stats.ai_heading') }}</h2>
                    <p class="muted" style="margin:0;font-size:.9rem;">{{ __('music::messages.stats.ai_hint') }}</p>
                </div>
                <button type="button" id="ai-btn" class="btn btn-primary">{{ __('music::messages.stats.ai_run') }}</button>
            </div>
            <div id="ai-out" class="ai-out hidden"></div>
        </div>
    @endif

    <div class="stats-grid">
        {{-- Top artists --}}
        <div class="card card-pad">
            <h3 class="stats-h">{{ __('music::messages.stats.top_artists') }}</h3>
            @forelse ($topArtists as $i => $a)
                <div class="bar-row">
                    <span class="bar-label" title="{{ $a['name'] }}">{{ $a['name'] }}</span>
                    <span class="bar-track"><span class="bar-fill" style="width:{{ max(4, round($a['plays'] / $maxArtist * 100)) }}%;background:{{ $palette[$i % count($palette)] }};"></span></span>
                    <span class="bar-num num">{{ $a['plays'] }}</span>
                </div>
            @empty
                <p class="muted">—</p>
            @endforelse
        </div>

        {{-- Genres --}}
        <div class="card card-pad">
            <h3 class="stats-h">{{ __('music::messages.stats.by_genre') }}</h3>
            @forelse ($genres as $i => $g)
                <div class="bar-row">
                    <span class="bar-label">{{ $g['name'] }}</span>
                    <span class="bar-track"><span class="bar-fill" style="width:{{ max(4, round($g['plays'] / $maxGenre * 100)) }}%;background:{{ $palette[$i % count($palette)] }};"></span></span>
                    <span class="bar-num num">{{ $g['plays'] }}</span>
                </div>
            @empty
                <p class="muted">—</p>
            @endforelse
        </div>

        {{-- Decades --}}
        <div class="card card-pad">
            <h3 class="stats-h">{{ __('music::messages.stats.by_decade') }}</h3>
            @forelse ($decades as $decade => $n)
                <div class="bar-row">
                    <span class="bar-label">{{ $decade }}s</span>
                    <span class="bar-track"><span class="bar-fill" style="width:{{ max(4, round($n / $maxDecade * 100)) }}%;"></span></span>
                    <span class="bar-num num">{{ $n }}</span>
                </div>
            @empty
                <p class="muted">—</p>
            @endforelse
        </div>

        {{-- Most played --}}
        <div class="card card-pad">
            <h3 class="stats-h">{{ __('music::messages.stats.most_played') }}</h3>
            @include('music::partials.track-list', ['rows' => $mostPlayed, 'metric' => 'plays'])
        </div>

        {{-- Lost favourites --}}
        <div class="card card-pad">
            <h3 class="stats-h">{{ __('music::messages.stats.lost_favorites') }}</h3>
            <p class="muted" style="font-size:.82rem;margin-top:-6px;">{{ __('music::messages.stats.lost_hint') }}</p>
            @include('music::partials.track-list', ['rows' => $lostFavorites, 'metric' => 'last'])
        </div>

        {{-- Top rated --}}
        <div class="card card-pad">
            <h3 class="stats-h">{{ __('music::messages.stats.top_rated') }}</h3>
            @include('music::partials.track-list', ['rows' => $topRated, 'metric' => 'rating'])
        </div>
    </div>
@endsection

@push('scripts')
@if ($aiConfigured)
<script>
(() => {
    const btn = document.getElementById('ai-btn');
    const out = document.getElementById('ai-out');
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const T = @js(['run' => __('music::messages.stats.ai_run'), 'running' => __('music::messages.stats.ai_running')]);

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        btn.textContent = T.running;
        out.classList.remove('hidden');
        out.textContent = '';
        try {
            const res = await fetch(@js(route('music.stats.analyze')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: '{}',
            });
            const data = await res.json();
            out.textContent = res.ok ? data.analysis : (data.error || 'Failed.');
        } catch (e) {
            out.textContent = e.message;
        } finally {
            btn.disabled = false;
            btn.textContent = T.run;
        }
    });
})();
</script>
@endif
@endpush
