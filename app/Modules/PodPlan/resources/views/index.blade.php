@extends('layouts.app')
@section('title', __('podplan::messages.title'))
@section('bodyClass', 'ctx-podplan')
@push('head') @include('podplan::partials.styles') @endpush

@section('content')
<div class="pp">
    @include('podplan::partials.nav', ['active' => 'projects'])
    <div class="pp-top">
        <div class="pp-grow">
            <h1>{{ __('podplan::messages.index.heading') }}</h1>
            <p class="pp-muted">{{ __('podplan::messages.index.subheading') }}</p>
        </div>
        <a class="pp-btn pp-btn--primary" href="{{ route('podplan.projects.create') }}">{{ __('podplan::messages.index.new') }}</a>
    </div>

    @if ($cards->isEmpty())
        <div class="pp-card pp-muted">{{ __('podplan::messages.index.empty') }}</div>
    @else
        <div class="pp-grid">
            @foreach ($cards as $card)
                @php($p = $card['project'])
                <div class="pp-card pp-stack" style="gap:14px;padding:22px;border-radius:var(--r-card);">
                    <div>
                        <h2 style="font-size:1.3rem;">{{ $p->name }}</h2>
                        <p class="pp-muted pp-small">
                            {{ __('podplan::messages.shoot_types.'.$p->shoot_type) }}
                            · {{ $p->shoot_date ? $p->shoot_date->translatedFormat('j M Y') : __('podplan::messages.index.no_date') }}
                            @if ($p->location) · {{ $p->location }} @endif
                        </p>
                    </div>
                    <div class="pp-row" style="gap:6px;">
                        <span class="pp-tag">{{ trans_choice('podplan::messages.index.cameras', $card['cameras'], ['count' => $card['cameras']]) }}</span>
                        @if ($p->live_mixing)
                            <span class="pp-tag pp-tag--live">{{ __('podplan::messages.index.live') }}</span>
                        @else
                            <span class="pp-tag">{{ __('podplan::messages.index.record_only') }}</span>
                        @endif
                    </div>
                    <div class="pp-stack" style="gap:6px;">
                        @if ($card['total'])
                            <div class="pp-row" style="align-items:baseline;gap:8px;">
                                <span class="pp-big pp-big--sm">{{ $card['packed'] }}</span>
                                <span class="pp-muted pp-small">{{ __('podplan::messages.checklist.of_packed', ['total' => $card['total']]) }}</span>
                            </div>
                        @else
                            <p class="pp-muted pp-small">{{ __('podplan::messages.index.nothing') }}</p>
                        @endif
                        <div class="pp-progress" style="height:6px;"><span style="width: {{ $card['total'] ? round($card['packed'] / $card['total'] * 100) : 0 }}%"></span></div>
                    </div>
                    <div class="pp-row">
                        <a class="pp-btn pp-btn--tight" href="{{ route('podplan.plan', $p) }}">{{ __('podplan::messages.index.open_plan') }}</a>
                        <a class="pp-btn pp-btn--tight" href="{{ route('podplan.checklist', $p) }}">{{ __('podplan::messages.index.open_checklist') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
