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
                        <button type="button" id="nc-toggle" class="btn btn-ghost"
                                data-store-url="{{ route('receipts.clients.store') }}"
                                title="{{ __('receipts::messages.allocations.new_client') }}">+</button>
                    </div>
                    @error('client_id')<div class="field-error">{{ $message }}</div>@enderror

                    {{-- Inline "add client" panel — fields carry no name= so they never submit with the allocation. --}}
                    <div id="nc-panel" hidden style="margin-top:12px;padding:14px;border:1px solid var(--border);border-radius:12px;background:var(--surface-2);">
                        <div style="font-weight:600;margin-bottom:10px;">{{ __('receipts::messages.allocations.new_client') }}</div>
                        <div class="form-row">
                            <label for="nc-name">{{ __('receipts::messages.clients.name') }}</label>
                            <input id="nc-name" class="input" autocomplete="off">
                        </div>
                        <div id="nc-error" class="field-error" hidden></div>
                        <div class="flex gap-sm">
                            <button type="button" id="nc-save" class="btn btn-primary btn-sm">{{ __('receipts::messages.clients.save') }}</button>
                            <button type="button" id="nc-cancel" class="btn btn-ghost btn-sm">{{ __('receipts::messages.create.cancel') }}</button>
                        </div>
                    </div>
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

// Inline "add client" — create via fetch, then select the new option in place.
(function () {
    const toggle = document.getElementById('nc-toggle');
    const panel = document.getElementById('nc-panel');
    if (!toggle || !panel) return;

    const select = document.getElementById('client_id');
    const nameInput = document.getElementById('nc-name');
    const errBox = document.getElementById('nc-error');
    const saveBtn = document.getElementById('nc-save');
    const cancelBtn = document.getElementById('nc-cancel');
    const storeUrl = toggle.dataset.storeUrl;
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const errFallback = @js(__('receipts::messages.allocations.client_error'));

    function open() { panel.hidden = false; errBox.hidden = true; nameInput.focus(); }
    function close() { panel.hidden = true; errBox.hidden = true; nameInput.value = ''; }

    toggle.addEventListener('click', () => panel.hidden ? open() : close());
    cancelBtn.addEventListener('click', close);

    async function save() {
        errBox.hidden = true;
        saveBtn.disabled = true;
        try {
            const res = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ name: nameInput.value.trim() }),
            });
            if (res.status === 422) {
                const data = await res.json();
                errBox.textContent = Object.values(data.errors)[0][0];
                errBox.hidden = false;
                return;
            }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const client = await res.json();
            select.add(new Option(client.name, client.id, true, true));
            close();
        } catch (e) {
            errBox.textContent = errFallback;
            errBox.hidden = false;
        } finally {
            saveBtn.disabled = false;
        }
    }

    saveBtn.addEventListener('click', save);
    nameInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); save(); } });
})();
</script>
@endpush
