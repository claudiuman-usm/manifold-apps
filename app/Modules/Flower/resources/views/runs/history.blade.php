@extends('layouts.app')
@use('App\Modules\Flower\Support\Duration')

@section('title', __('flower::messages.history.heading'))
@section('bodyClass', 'ctx-flower')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('flower.index') }}">{{ __('flower::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ $template->name }}</span>
    </div>

    <div class="row-between page-head">
        <div>
            <h1>{{ __('flower::messages.history.heading') }}</h1>
            <p>{{ __('flower::messages.history.subheading', ['template' => $template->name]) }}</p>
        </div>
        @if ($template->steps->isNotEmpty())
            <div class="flex gap-sm" style="align-items:center;">
                @include('flower::runs._launch', ['template' => $template, 'label' => __('flower::messages.history.start_run')])
            </div>
        @endif
    </div>

    @if ($runs->isEmpty())
        <div class="empty-state card card-pad">{{ __('flower::messages.history.empty') }}</div>
    @else
        @php($avgTotal = collect($template->steps)->sum(fn ($s) => $averages[$s->id] ?? 0))

        {{-- Desktop: full matrix table (scrolls horizontally if very wide). --}}
        <div class="card history-table">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>{{ __('flower::messages.history.run') }}</th>
                            <th>{{ __('flower::messages.history.date') }}</th>
                            @foreach ($template->steps as $step)
                                <th style="text-align:right;">{{ $step->name }}</th>
                            @endforeach
                            <th style="text-align:right;">{{ __('flower::messages.history.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runs as $i => $run)
                            @php($byStep = $run->stepLogs->keyBy('step_id'))
                            <tr>
                                <td style="font-weight:600;">#{{ $runs->count() - $i }}</td>
                                <td class="muted">{{ optional($run->completed_at)->format('Y-m-d H:i') }}</td>
                                @foreach ($template->steps as $step)
                                    <td class="num" style="text-align:right;">
                                        {{ Duration::format(optional($byStep->get($step->id))->duration_seconds) }}
                                    </td>
                                @endforeach
                                <td class="num" style="text-align:right;font-weight:600;">
                                    {{ Duration::format($run->stepLogs->sum('duration_seconds')) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="font-weight:600;">{{ __('flower::messages.history.average_row') }}</td>
                            @foreach ($template->steps as $step)
                                <td class="num" style="text-align:right;">
                                    {{ $averages[$step->id] !== null ? Duration::format($averages[$step->id]) : '—' }}
                                </td>
                            @endforeach
                            <td class="num" style="text-align:right;font-weight:600;">{{ Duration::format($avgTotal) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Mobile: one card per run, plus an averages card. --}}
        <div class="history-cards">
            @foreach ($runs as $i => $run)
                @php($byStep = $run->stepLogs->keyBy('step_id'))
                <div class="card card-pad history-card">
                    <div class="history-card-head">
                        <span class="history-card-title">#{{ $runs->count() - $i }}</span>
                        <span class="muted">{{ optional($run->completed_at)->format('Y-m-d H:i') }}</span>
                        <span class="history-card-total num">{{ Duration::format($run->stepLogs->sum('duration_seconds')) }}</span>
                    </div>
                    <dl class="history-card-steps">
                        @foreach ($template->steps as $step)
                            <div class="history-card-row">
                                <dt>{{ $step->name }}</dt>
                                <dd class="num">{{ Duration::format(optional($byStep->get($step->id))->duration_seconds) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach

            <div class="card card-pad history-card history-card-avg">
                <div class="history-card-head">
                    <span class="history-card-title">{{ __('flower::messages.history.average_row') }}</span>
                    <span class="history-card-total num">{{ Duration::format($avgTotal) }}</span>
                </div>
                <dl class="history-card-steps">
                    @foreach ($template->steps as $step)
                        <div class="history-card-row">
                            <dt>{{ $step->name }}</dt>
                            <dd class="num">{{ $averages[$step->id] !== null ? Duration::format($averages[$step->id]) : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    @endif

    <div class="mt">
        <a href="{{ route('flower.index') }}" class="btn">{{ __('flower::messages.history.back') }}</a>
    </div>
@endsection
