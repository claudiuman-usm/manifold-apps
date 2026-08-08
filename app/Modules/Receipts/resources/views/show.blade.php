@extends('layouts.app')
@section('title', __('receipts::messages.show.edit_heading'))
@section('bodyClass', 'ctx-receipts')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('receipts.index') }}">{{ __('receipts::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ $receipt->merchant ?: __('receipts::messages.show.edit_heading') }}</span>
    </div>

    <div class="page-head">
        <h1>{{ $receipt->status === 'review' ? __('receipts::messages.show.review_heading') : __('receipts::messages.show.edit_heading') }}</h1>
    </div>

    <div class="receipt-detail">
        {{-- Image + original/square toggle --}}
        <div class="card card-pad">
            <div class="locale-switch" style="margin-bottom:12px;">
                <a href="#" class="active" data-variant="square" id="tab-square">{{ __('receipts::messages.show.square') }}</a>
                <a href="#" data-variant="original" id="tab-original">{{ __('receipts::messages.show.original') }}</a>
            </div>
            <img id="receipt-image" src="{{ route('receipts.image', [$receipt, 'square']) }}"
                 alt="" style="width:100%;border-radius:var(--r-el);display:block;">
        </div>

        {{-- Editable fields --}}
        <div class="card card-pad">
            <form method="POST" action="{{ route('receipts.update', $receipt) }}">
                @csrf
                @method('PUT')

                <div class="form-row">
                    <label for="merchant">{{ __('receipts::messages.show.merchant') }}</label>
                    <input id="merchant" name="merchant" class="input" value="{{ old('merchant', $receipt->merchant) }}">
                </div>

                <div class="flex" style="gap:12px;align-items:flex-start;">
                    <div class="form-row" style="flex:1;">
                        <label for="amount">{{ __('receipts::messages.show.amount') }}</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0" class="input num"
                               value="{{ old('amount', $receipt->amount) }}">
                    </div>
                    <div class="form-row" style="width:110px;">
                        <label for="currency">{{ __('receipts::messages.show.currency') }}</label>
                        <input id="currency" name="currency" maxlength="3" class="input"
                               value="{{ old('currency', $receipt->currency) }}" style="text-transform:uppercase;">
                    </div>
                </div>

                <div class="form-row">
                    <label for="purchased_at">{{ __('receipts::messages.show.date') }}</label>
                    <input id="purchased_at" name="purchased_at" type="date" class="input"
                           value="{{ old('purchased_at', optional($receipt->purchased_at)->format('Y-m-d')) }}">
                </div>

                <div class="form-row">
                    <label for="category_id">{{ __('receipts::messages.show.category') }}</label>
                    <select id="category_id" name="category_id" class="select">
                        <option value="">{{ __('receipts::messages.show.category_none') }}</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected((int) old('category_id', $receipt->category_id) === $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Client is assigned when the receipt is allocated to an invoice, not at upload time. --}}

                @if ($receipt->allocation)
                    <div class="form-row">
                        <label>{{ __('receipts::messages.show.allocated_to') }}</label>
                        <a href="{{ route('receipts.allocations.show', $receipt->allocation) }}" class="badge">{{ $receipt->allocation->invoice_number }}</a>
                    </div>
                @endif

                <div class="form-row">
                    <label for="notes">{{ __('receipts::messages.show.notes') }}</label>
                    <textarea id="notes" name="notes" class="input" rows="2">{{ old('notes', $receipt->notes) }}</textarea>
                </div>

                <div class="flex">
                    <button type="submit" class="btn btn-primary">{{ __('receipts::messages.show.save') }}</button>
                    <a href="{{ route('receipts.index') }}" class="btn btn-ghost">{{ __('receipts::messages.show.back') }}</a>
                </div>
            </form>

            <form method="POST" action="{{ route('receipts.destroy', $receipt) }}" style="margin-top:16px;"
                  onsubmit="return confirm(@js(__('receipts::messages.show.delete_confirm')))">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm">{{ __('receipts::messages.show.delete') }}</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const img = document.getElementById('receipt-image');
    const base = @js(route('receipts.image', [$receipt, '__V__']));
    document.querySelectorAll('.locale-switch a[data-variant]').forEach((tab) => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.locale-switch a[data-variant]').forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');
            img.src = base.replace('__V__', tab.dataset.variant);
        });
    });
})();
</script>
@endpush
