@extends('layouts.app')
@section('title', $allocation->title)
@section('bodyClass', 'ctx-receipts')
@php($fmt = fn ($n) => number_format((float) $n, 2))

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('receipts.index') }}">{{ __('receipts::messages.title') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('receipts.allocations.index') }}">{{ __('receipts::messages.allocations.heading') }}</a>
        <span class="sep">/</span>
        <span>{{ $allocation->title }}</span>
    </div>

    <div class="row-between page-head">
        <div>
            <h1>{{ $allocation->title }}</h1>
            <p>{{ $allocation->client?->name }} · {{ optional($allocation->period_month)->translatedFormat('F Y') ?: '—' }}</p>
        </div>
        <div class="flex gap-sm">
            <a href="{{ route('receipts.allocations.pdf', $allocation) }}" target="_blank" class="btn btn-primary">{{ __('receipts::messages.allocations.pdf') }}</a>
            <form method="POST" action="{{ route('receipts.allocations.destroy', $allocation) }}"
                  onsubmit="return confirm(@js(__('receipts::messages.allocations.delete_confirm')))">
                @csrf @method('DELETE')
                <button class="btn btn-danger">{{ __('receipts::messages.allocations.delete') }}</button>
            </form>
        </div>
    </div>

    {{-- In this allocation --}}
    <div class="card card-pad" style="margin-bottom:22px;">
        <div class="row-between" style="margin-bottom:12px;">
            <h2 style="font-size:1.1rem;margin:0;">{{ __('receipts::messages.allocations.in_allocation') }}</h2>
            <div class="num" style="font-size:1.3rem;">{{ __('receipts::messages.allocations.total') }}: {{ $fmt($total) }} <span class="faint">{{ $baseCurrency }}</span></div>
        </div>

        @if ($allocation->receipts->isEmpty())
            <p class="muted">{{ __('receipts::messages.allocations.none_yet') }}</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <tbody>
                        @foreach ($allocation->receipts as $r)
                            <tr>
                                <td class="muted" style="width:110px;">{{ optional($r->purchased_at)->format('d M Y') ?: '—' }}</td>
                                <td style="font-weight:600;"><a href="{{ route('receipts.show', $r) }}">{{ $r->merchant ?: '—' }}</a></td>
                                <td>@if ($r->category)<span class="badge">{{ $r->category->name }}</span>@endif</td>
                                <td class="num" style="text-align:right;">{{ $fmt($r->amount) }} <span class="faint">{{ $r->currency }}</span></td>
                                <td style="text-align:right;">
                                    <form method="POST" action="{{ route('receipts.allocations.detach', [$allocation, $r]) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger">{{ __('receipts::messages.allocations.remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Add unallocated receipts --}}
    <div class="card card-pad">
        <h2 style="font-size:1.1rem;margin:0 0 12px;">{{ __('receipts::messages.allocations.add_receipts') }}</h2>
        @if ($candidates->isEmpty())
            <p class="muted">{{ __('receipts::messages.allocations.no_candidates') }}</p>
        @else
            <form method="POST" action="{{ route('receipts.allocations.attach', $allocation) }}">
                @csrf
                <div class="table-wrap">
                    <table class="data">
                        <tbody>
                            @foreach ($candidates as $r)
                                <tr>
                                    <td style="width:34px;"><input type="checkbox" name="receipt_ids[]" value="{{ $r->id }}" style="accent-color:var(--accent);width:16px;height:16px;"></td>
                                    <td class="muted" style="width:110px;">{{ optional($r->purchased_at)->format('d M Y') ?: '—' }}</td>
                                    <td style="font-weight:600;">{{ $r->merchant ?: '—' }}</td>
                                    <td>@if ($r->category)<span class="badge">{{ $r->category->name }}</span>@endif</td>
                                    <td class="num" style="text-align:right;">{{ $fmt($r->amount) }} <span class="faint">{{ $r->currency }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary mt">{{ __('receipts::messages.allocations.add_selected') }}</button>
            </form>
        @endif
    </div>
@endsection
