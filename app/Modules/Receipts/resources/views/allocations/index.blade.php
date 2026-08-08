@extends('layouts.app')
@section('title', __('receipts::messages.allocations.heading'))
@section('bodyClass', 'ctx-receipts')
@php($fmt = fn ($n) => number_format((float) $n, 2))

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('receipts.index') }}">{{ __('receipts::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ __('receipts::messages.allocations.heading') }}</span>
    </div>

    <div class="row-between page-head">
        <div>
            <h1>{{ __('receipts::messages.allocations.heading') }}</h1>
            <p>{{ __('receipts::messages.allocations.subheading') }}</p>
        </div>
        <a href="{{ route('receipts.allocations.create') }}" class="btn btn-primary">+ {{ __('receipts::messages.allocations.add') }}</a>
    </div>

    @if ($allocations->isEmpty())
        <div class="empty-state card card-pad">{{ __('receipts::messages.allocations.empty') }}</div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>{{ __('receipts::messages.allocations.title') }}</th>
                            <th>{{ __('receipts::messages.allocations.client') }}</th>
                            <th>{{ __('receipts::messages.allocations.period') }}</th>
                            <th style="text-align:right;">{{ __('receipts::messages.allocations.receipts') }}</th>
                            <th style="text-align:right;">{{ __('receipts::messages.allocations.total') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($allocations as $a)
                            <tr>
                                <td style="font-weight:600;"><a href="{{ route('receipts.allocations.show', $a) }}">{{ $a->title }}</a></td>
                                <td>{{ $a->client?->name ?: '—' }}</td>
                                <td class="muted">{{ optional($a->period_month)->translatedFormat('F Y') ?: '—' }}</td>
                                <td class="num" style="text-align:right;">{{ $a->receipts_count }}</td>
                                <td class="num" style="text-align:right;">{{ $fmt($a->receipts_sum_amount) }} <span class="faint">{{ $baseCurrency }}</span></td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <div class="flex gap-sm" style="justify-content:flex-end;">
                                        <a href="{{ route('receipts.allocations.pdf', $a) }}" target="_blank" class="btn btn-sm btn-ghost">{{ __('receipts::messages.allocations.pdf') }}</a>
                                        <a href="{{ route('receipts.allocations.show', $a) }}" class="btn btn-sm">{{ __('receipts::messages.clients.edit') }}</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
