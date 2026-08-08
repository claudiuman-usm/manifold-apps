@extends('layouts.app')
@section('title', __('receipts::messages.allocations.create_heading'))
@section('bodyClass', 'ctx-receipts')

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
        <div class="card card-pad" style="max-width:520px;">
            <p class="muted" style="margin-top:0;">{{ __('receipts::messages.allocations.need_client_first') }}</p>
            <a href="{{ route('receipts.clients.create') }}" class="btn btn-primary">+ {{ __('receipts::messages.clients.add') }}</a>
        </div>
    @else
        <form method="POST" action="{{ route('receipts.allocations.store') }}" class="card card-pad" style="max-width:520px;">
            @csrf
            <div class="form-row">
                <label for="client_id">{{ __('receipts::messages.allocations.client') }}</label>
                <select id="client_id" name="client_id" class="select" required>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}" @selected((int) old('client_id') === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('client_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-row">
                <label for="title">{{ __('receipts::messages.allocations.title') }}</label>
                <input id="title" name="title" class="input" value="{{ old('title') }}" required>
                @error('title')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-row">
                <label for="period_month">{{ __('receipts::messages.allocations.period') }}</label>
                <input id="period_month" name="period_month" type="month" class="input" value="{{ old('period_month', now()->format('Y-m')) }}">
                @error('period_month')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-row">
                <label for="notes">{{ __('receipts::messages.allocations.notes') }}</label>
                <textarea id="notes" name="notes" class="input" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div class="flex">
                <button class="btn btn-primary">{{ __('receipts::messages.allocations.create') }}</button>
                <a href="{{ route('receipts.allocations.index') }}" class="btn btn-ghost">{{ __('receipts::messages.allocations.back') }}</a>
            </div>
        </form>
    @endif
@endsection
