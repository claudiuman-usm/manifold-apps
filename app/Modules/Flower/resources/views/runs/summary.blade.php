@extends('layouts.app')
@use('App\Modules\Flower\Support\Duration')

@section('title', __('flower::messages.summary.heading'))
@section('bodyClass', 'ctx-flower')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('flower.index') }}">{{ __('flower::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ $template->name }}</span>
    </div>

    <div class="page-head">
        <h1>{{ __('flower::messages.summary.heading') }}</h1>
        <p>{{ __('flower::messages.summary.subheading') }}</p>
    </div>

    <div class="card card-pad" style="margin-bottom:20px;">
        <div class="row-between">
            <div>
                <div class="muted" style="font-size:.85rem;">{{ $template->name }}</div>
                <div style="font-size:1.4rem;font-weight:700;" class="num">
                    {{ __('flower::messages.summary.total') }}: {{ Duration::format($totalDuration) }}
                </div>
            </div>
            <div class="flex gap-sm" style="align-items:center;">
                @include('flower::runs._launch', ['template' => $template, 'label' => __('flower::messages.summary.start_over')])
                <a href="{{ route('flower.templates.history', $template) }}" class="btn">
                    {{ __('flower::messages.summary.view_history') }}
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>{{ __('flower::messages.summary.step') }}</th>
                        <th style="text-align:right;">{{ __('flower::messages.summary.duration') }}</th>
                        <th style="text-align:right;">{{ __('flower::messages.summary.average') }}</th>
                        <th style="text-align:right;">{{ __('flower::messages.summary.delta') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['step']->name }}</td>
                            <td class="num" style="text-align:right;">{{ Duration::format($row['duration']) }}</td>
                            <td class="num" style="text-align:right;">
                                {{ $row['average'] !== null ? Duration::format($row['average']) : '—' }}
                            </td>
                            <td class="num" style="text-align:right;">
                                @if ($row['delta'] === null)
                                    —
                                @elseif ($row['delta'] <= 0)
                                    <span class="delta-faster">−{{ Duration::format(abs($row['delta'])) }} {{ __('flower::messages.summary.faster') }}</span>
                                @else
                                    <span class="delta-slower">+{{ Duration::format($row['delta']) }} {{ __('flower::messages.summary.slower') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt">
        <a href="{{ route('flower.index') }}" class="btn">{{ __('flower::messages.summary.back') }}</a>
    </div>
@endsection
