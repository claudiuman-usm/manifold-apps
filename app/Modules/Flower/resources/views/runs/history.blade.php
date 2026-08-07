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
            <form method="POST" action="{{ route('flower.runs.start', $template) }}">
                @csrf
                <button type="submit" class="btn btn-primary">▶ {{ __('flower::messages.history.start_run') }}</button>
            </form>
        @endif
    </div>

    @if ($runs->isEmpty())
        <div class="empty-state card card-pad">{{ __('flower::messages.history.empty') }}</div>
    @else
        <div class="card">
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
                            @php($avgTotal = 0)
                            @foreach ($template->steps as $step)
                                @php($avgTotal += $averages[$step->id] ?? 0)
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
    @endif

    <div class="mt">
        <a href="{{ route('flower.index') }}" class="btn">{{ __('flower::messages.history.back') }}</a>
    </div>
@endsection
