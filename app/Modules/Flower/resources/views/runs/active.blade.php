@extends('layouts.app')
@use('App\Modules\Flower\Support\Duration')

@section('title', $template->name)
@section('bodyClass', 'ctx-flower')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('flower.index') }}">{{ __('flower::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ $template->type?->client?->name }} · {{ $template->type?->name }} · {{ $template->name }}</span>
    </div>

    <div class="row-between page-head">
        <div>
            <h1>{{ $template->name }}</h1>
            <p>{{ __('flower::messages.run.progress', ['done' => $doneCount, 'total' => $total]) }}</p>
        </div>
        <form method="POST" action="{{ route('flower.runs.destroy', $run) }}"
              onsubmit="return confirm(@js(__('flower::messages.run.cancel_confirm')))">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">{{ __('flower::messages.run.cancel') }}</button>
        </form>
    </div>

    <div class="progress-track">
        <div class="progress-fill" style="width: {{ $total ? round($doneCount / $total * 100) : 0 }}%"></div>
    </div>

    {{-- Current-step timer hero --}}
    @php($currentRow = $rows->firstWhere('is_current', true))
    @if ($currentRow)
        <div class="card timer-hero"
             id="timer-hero"
             data-elapsed="{{ $currentElapsedAtLoad }}"
             data-average="{{ $currentAverage !== null ? round($currentAverage) : '' }}"
             data-threshold="{{ $currentAverage !== null ? round($currentAverage) + 3 : '' }}">
            <div class="eyebrow">{{ __('flower::messages.run.current_step') }}</div>
            <div class="step-name">{{ $currentRow['step']->name }}</div>
            <div class="clock" id="clock">0:00</div>
            <div class="avg-note">
                @if ($currentAverage !== null)
                    {{ __('flower::messages.run.average') }}: {{ Duration::format($currentAverage) }}
                @else
                    {{ __('flower::messages.run.no_history') }}
                @endif
            </div>

            <div class="timer-actions mt">
                @if ($canGoBack)
                    <form method="POST" action="{{ route('flower.runs.back', $run) }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-lg">
                            ← {{ __('flower::messages.run.back') }}
                        </button>
                    </form>
                @endif
                <form method="POST" action="{{ route('flower.runs.advance', $run) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg">
                        @if ($isLastStep) {{ __('flower::messages.run.check_last') }}
                        @else {{ __('flower::messages.run.check') }} @endif
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- Full checklist. The active step is a checkbox you click to advance. --}}
    <ul class="run-steps">
        @foreach ($rows as $i => $row)
            @if ($row['is_current'])
                <li>
                    <form method="POST" action="{{ route('flower.runs.advance', $run) }}" class="run-step-form">
                        @csrf
                        <button type="submit" class="run-step current run-step-click"
                                title="{{ $isLastStep ? __('flower::messages.run.check_last') : __('flower::messages.run.check') }}">
                            <span class="marker check" aria-hidden="true"></span>
                            <span class="name">{{ $row['step']->name }}</span>
                            <span class="meta">
                                @if ($row['average'] !== null)~{{ Duration::format($row['average']) }}@endif
                            </span>
                        </button>
                    </form>
                </li>
            @else
                <li class="run-step {{ $row['is_done'] ? 'done' : '' }}">
                    <span class="marker">{{ $row['is_done'] ? '✓' : $i + 1 }}</span>
                    <span class="name">{{ $row['step']->name }}</span>
                    <span class="meta">
                        @if ($row['is_done'])
                            {{ Duration::format($row['duration']) }}
                        @elseif ($row['average'] !== null)
                            ~{{ Duration::format($row['average']) }}
                        @endif
                    </span>
                </li>
            @endif
        @endforeach
    </ul>

    {{-- Nudge — phone-style glass toast that slides in from the top --}}
    <div class="nudge-scrim" id="nudge-scrim" aria-hidden="true"></div>
    <div class="nudge-toast" id="nudge">
        <div class="nudge-toast-text">
            <strong>{{ __('flower::messages.nudge.title') }}</strong>
            <span>{{ __('flower::messages.nudge.body') }}</span>
        </div>
        <form method="POST" action="{{ route('flower.runs.advance', $run) }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary">
                @if ($isLastStep) {{ __('flower::messages.run.check_last') }}
                @else {{ __('flower::messages.run.check') }} @endif
            </button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const hero = document.getElementById('timer-hero');
    if (!hero) return;

    const clock = document.getElementById('clock');
    const nudge = document.getElementById('nudge');
    const nudgeScrim = document.getElementById('nudge-scrim');
    const base = parseInt(hero.dataset.elapsed || '0', 10);
    const threshold = hero.dataset.threshold === '' ? null : parseInt(hero.dataset.threshold, 10);
    const loadedAt = Date.now();

    function fmt(total) {
        const m = Math.floor(total / 60);
        const s = total % 60;
        return m + ':' + String(s).padStart(2, '0');
    }

    function tick() {
        const elapsed = base + Math.floor((Date.now() - loadedAt) / 1000);
        clock.textContent = fmt(elapsed);

        const over = threshold !== null && elapsed > threshold;
        clock.classList.toggle('over', over);
        if (nudge) nudge.classList.toggle('show', over);
        if (nudgeScrim) nudgeScrim.classList.toggle('show', over);
    }

    tick();
    setInterval(tick, 1000);
})();

// Warn before leaving a running flow (tab close, refresh, breadcrumb/nav links, browser back).
// The run's own actions (advance / back / check / cancel) submit forms, so we drop the guard
// on any in-page form submit — those are intentional and the run state is saved server-side.
(function () {
    let leaving = false;
    document.addEventListener('submit', function () { leaving = true; }, true);

    window.addEventListener('beforeunload', function (e) {
        if (leaving) return;
        e.preventDefault();
        e.returnValue = '';
        return '';
    });
})();
</script>
@endpush
