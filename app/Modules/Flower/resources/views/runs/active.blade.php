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

            <form method="POST" action="{{ route('flower.runs.advance', $run) }}" class="mt">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg">
                    @if ($isLastStep) ✓ {{ __('flower::messages.run.check_last') }}
                    @else ✓ {{ __('flower::messages.run.check') }} @endif
                </button>
            </form>
        </div>
    @endif

    {{-- Full checklist --}}
    <ul class="run-steps">
        @foreach ($rows as $i => $row)
            <li class="run-step {{ $row['is_done'] ? 'done' : '' }} {{ $row['is_current'] ? 'current' : '' }}">
                <span class="marker">
                    @if ($row['is_done']) ✓ @else {{ $i + 1 }} @endif
                </span>
                <span class="name">{{ $row['step']->name }}</span>
                <span class="meta">
                    @if ($row['is_done'])
                        {{ Duration::format($row['duration']) }}
                    @elseif ($row['average'] !== null)
                        ~{{ Duration::format($row['average']) }}
                    @endif
                </span>
            </li>
        @endforeach
    </ul>

    {{-- Nudge popup --}}
    <div class="nudge" id="nudge" hidden>
        <strong>{{ __('flower::messages.nudge.title') }}</strong>
        <p>{{ __('flower::messages.nudge.body') }}</p>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const hero = document.getElementById('timer-hero');
    if (!hero) return;

    const clock = document.getElementById('clock');
    const nudge = document.getElementById('nudge');
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
        nudge.hidden = !over;
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush
