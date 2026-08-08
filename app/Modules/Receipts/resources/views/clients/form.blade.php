@extends('layouts.app')
@section('title', $client->exists ? __('receipts::messages.clients.edit_heading') : __('receipts::messages.clients.create_heading'))
@section('bodyClass', 'ctx-receipts')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('receipts.index') }}">{{ __('receipts::messages.title') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('receipts.clients.index') }}">{{ __('receipts::messages.clients.heading') }}</a>
        <span class="sep">/</span>
        <span>{{ $client->exists ? $client->name : __('receipts::messages.clients.create_heading') }}</span>
    </div>

    <div class="page-head">
        <h1>{{ $client->exists ? __('receipts::messages.clients.edit_heading') : __('receipts::messages.clients.create_heading') }}</h1>
    </div>

    <form method="POST"
          action="{{ $client->exists ? route('receipts.clients.update', $client) : route('receipts.clients.store') }}"
          class="card card-pad" style="max-width:520px;">
        @csrf
        @if ($client->exists) @method('PUT') @endif

        <div class="form-row">
            <label for="name">{{ __('receipts::messages.clients.name') }}</label>
            <input id="name" name="name" class="input" value="{{ old('name', $client->name) }}" required autofocus>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
            <label for="notes">{{ __('receipts::messages.clients.notes') }}</label>
            <textarea id="notes" name="notes" class="input" rows="2">{{ old('notes', $client->notes) }}</textarea>
        </div>

        <div class="flex">
            <button class="btn btn-primary">{{ __('receipts::messages.clients.save') }}</button>
            <a href="{{ route('receipts.clients.index') }}" class="btn btn-ghost">{{ __('receipts::messages.clients.back') }}</a>
        </div>
    </form>
@endsection
