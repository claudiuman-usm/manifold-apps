@extends('layouts.app')
@section('title', __('receipts::messages.title'))
@section('bodyClass', 'ctx-receipts')

@php
    $palette = ['#f59e0b', '#14b8a6', '#7c6cf6', '#38bdf8', '#f472b6', '#34d399', '#fb923c', '#94a3b8'];
    $fmt = fn ($n) => number_format((float) $n, 2);
@endphp

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <span>{{ __('receipts::messages.title') }}</span>
    </div>

    <div class="row-between page-head">
        <div>
            <h1>{{ __('receipts::messages.index.heading') }}</h1>
            <p>{{ __('receipts::messages.index.subheading') }}</p>
        </div>
        <a href="{{ route('receipts.create') }}" class="btn btn-primary">+ {{ __('receipts::messages.index.add') }}</a>
    </div>

    {{-- Dashboard: month total + category donut --}}
    <div class="card card-pad" style="margin-bottom:22px;">
        <div class="row-between" style="align-items:flex-start;">
            <div>
                <div class="flex gap-sm" style="margin-bottom:10px;">
                    <a href="{{ route('receipts.index', ['month' => now()->format('Y-m')]) }}"
                       class="btn btn-sm {{ $monthParam !== 'all' ? 'btn-primary' : 'btn-ghost' }}">{{ __('receipts::messages.index.this_month') }}</a>
                    <a href="{{ route('receipts.index', ['month' => 'all']) }}"
                       class="btn btn-sm {{ $monthParam === 'all' ? 'btn-primary' : 'btn-ghost' }}">{{ __('receipts::messages.index.all_time') }}</a>
                </div>
                <div class="faint" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;">
                    {{ __('receipts::messages.index.total') }} · {{ $month->translatedFormat('F Y') }}
                </div>
                <div class="num" style="font-size:2.4rem;font-weight:300;letter-spacing:-.02em;">
                    {{ $fmt($monthTotal) }} <span style="font-size:1.1rem;" class="muted">{{ $baseCurrency }}</span>
                </div>
            </div>

            @if ($breakdown->isNotEmpty() && $monthTotal > 0)
                @php $circ = 2 * M_PI * 52; $offset = 0; @endphp
                <div class="flex" style="gap:20px;align-items:center;">
                    <svg width="132" height="132" viewBox="0 0 132 132" style="flex:none;">
                        <circle cx="66" cy="66" r="52" fill="none" stroke="var(--panel-2)" stroke-width="18"/>
                        @foreach ($breakdown as $i => $row)
                            @php
                                $frac = $row['total'] / $monthTotal;
                                $len = $frac * $circ;
                                $color = $palette[$i % count($palette)];
                            @endphp
                            <circle cx="66" cy="66" r="52" fill="none" stroke="{{ $color }}" stroke-width="18"
                                    stroke-dasharray="{{ $len }} {{ $circ - $len }}"
                                    stroke-dashoffset="{{ -$offset }}"
                                    transform="rotate(-90 66 66)"/>
                            @php $offset += $len; @endphp
                        @endforeach
                    </svg>
                    <div style="font-size:.85rem;">
                        <div class="faint" style="text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;font-size:.72rem;">{{ __('receipts::messages.index.by_category') }}</div>
                        @foreach ($breakdown as $i => $row)
                            <div class="flex gap-sm" style="margin-bottom:4px;">
                                <span style="width:10px;height:10px;border-radius:3px;background:{{ $palette[$i % count($palette)] }};flex:none;"></span>
                                <span style="flex:1;">{{ $row['name'] }}</span>
                                <span class="num muted">{{ $fmt($row['total']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="muted" style="align-self:center;">{{ __('receipts::messages.index.no_spend') }}</div>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('receipts.index') }}" class="flex gap-sm" style="margin-bottom:18px;flex-wrap:wrap;">
        <input type="hidden" name="month" value="{{ $monthParam ?? '' }}">
        <input type="text" name="q" value="{{ $search }}" class="input" style="max-width:240px;"
               placeholder="{{ __('receipts::messages.index.search') }}">
        <select name="category" class="select" style="max-width:220px;" onchange="this.form.submit()">
            <option value="">{{ __('receipts::messages.index.all_categories') }}</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected($activeCategory === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </form>

    @if ($receipts->isEmpty())
        <div class="empty-state card card-pad">{{ __('receipts::messages.index.empty') }}</div>
    @else
        <div class="tools-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));">
            @foreach ($receipts as $r)
                <a href="{{ route('receipts.show', $r) }}" class="card receipt-card">
                    <div class="receipt-thumb" style="background-image:url('{{ route('receipts.image', [$r, 'square']) }}')">
                        @if ($r->status === 'review')
                            <span class="badge badge-warning receipt-badge">{{ __('receipts::messages.status.review') }}</span>
                        @endif
                    </div>
                    <div class="receipt-body">
                        <div class="row-between">
                            <strong>{{ $r->merchant ?: '—' }}</strong>
                            <span class="num">{{ $fmt($r->amount) }} <span class="faint">{{ $r->currency }}</span></span>
                        </div>
                        <div class="flex gap-sm" style="margin-top:6px;font-size:.82rem;">
                            <span class="muted">{{ optional($r->purchased_at)->format('d M Y') ?: '—' }}</span>
                            @if ($r->category)<span class="badge">{{ $r->category->name }}</span>@endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
