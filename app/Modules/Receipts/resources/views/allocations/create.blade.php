@extends('layouts.app')
@section('title', __('receipts::messages.allocations.create_heading'))
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
        <span>{{ __('receipts::messages.allocations.create_heading') }}</span>
    </div>

    <div class="page-head"><h1>{{ __('receipts::messages.allocations.create_heading') }}</h1></div>

    @if ($clients->isEmpty())
        <div class="card card-pad" style="max-width:560px;">
            <p class="muted" style="margin-top:0;">{{ __('receipts::messages.allocations.need_client_first') }}</p>
            <a href="{{ route('receipts.clients.create') }}" class="btn btn-primary">+ {{ __('receipts::messages.clients.add') }}</a>
        </div>
    @else
        <form method="POST" action="{{ route('receipts.allocations.store') }}" id="alloc-form">
            @csrf

            <div class="card card-pad" style="margin-bottom:20px;max-width:720px;">
                <div class="form-row">
                    <label for="invoice_number">{{ __('receipts::messages.allocations.invoice_number') }}</label>
                    <input id="invoice_number" name="invoice_number" class="input"
                           placeholder="{{ __('receipts::messages.allocations.invoice_placeholder') }}"
                           value="{{ old('invoice_number') }}" required autofocus>
                    @error('invoice_number')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-row">
                    <label for="client_id">{{ __('receipts::messages.allocations.client') }}</label>
                    <div class="flex gap-sm">
                        <select id="client_id" name="client_id" class="select" required style="flex:1;">
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}" @selected((int) old('client_id') === $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <a href="{{ route('receipts.clients.create') }}" target="_blank" class="btn btn-ghost" title="{{ __('receipts::messages.clients.add') }}">+</a>
                    </div>
                    @error('client_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Month filter + receipts to include --}}
            <div class="card card-pad" style="margin-bottom:20px;">
                <div class="row-between" style="margin-bottom:14px;">
                    <label for="month-filter" style="font-weight:600;margin:0;">{{ __('receipts::messages.allocations.filter_month') }}</label>
                    <select id="month-filter" name="period_month" class="select" style="max-width:200px;">
                        <option value="all">{{ __('receipts::messages.allocations.all_months') }}</option>
                        @foreach ($months as $m)
                            <option value="{{ $m }}" @selected($m === $currentMonth)>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->translatedFormat('M Y') }}</option>
                        @endforeach
                    </select>
                </div>

                @error('receipt_ids')<div class="field-error" style="margin-bottom:10px;">{{ $message }}</div>@enderror

                <div class="table-wrap">
                    <table class="data" id="candidates">
                        <tbody>
                            @foreach ($candidates as $r)
                                <tr data-month="{{ optional($r->purchased_at)->format('Y-m') ?: 'none' }}">
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
                <p id="no-receipts" class="muted" hidden style="text-align:center;padding:24px;">{{ __('receipts::messages.allocations.none_this_month') }}</p>
            </div>

            <div class="flex" style="flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">{{ __('receipts::messages.allocations.save_download') }}</button>
                <button type="submit" class="btn btn-ghost"
                        formaction="{{ route('receipts.allocations.preview') }}" formtarget="_blank" formnovalidate>
                    {{ __('receipts::messages.allocations.preview') }}
                </button>
                <a href="{{ route('receipts.allocations.index') }}" class="btn btn-ghost">{{ __('receipts::messages.allocations.back') }}</a>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    const select = document.getElementById('month-filter');
    if (!select) return;
    const rows = [...document.querySelectorAll('#candidates tr')];
    const empty = document.getElementById('no-receipts');

    function apply() {
        const val = select.value;
        let visible = 0;
        rows.forEach((r) => {
            const show = val === 'all' || r.dataset.month === val;
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        empty.hidden = visible > 0;
    }
    select.addEventListener('change', apply);
    apply();
})();
</script>
@endpush
