@extends('layouts.app')
@section('title', __('receipts::messages.clients.heading'))
@section('bodyClass', 'ctx-receipts')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('receipts.index') }}">{{ __('receipts::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ __('receipts::messages.clients.heading') }}</span>
    </div>

    <div class="row-between page-head">
        <div>
            <h1>{{ __('receipts::messages.clients.heading') }}</h1>
            <p>{{ __('receipts::messages.clients.subheading') }}</p>
        </div>
        <a href="{{ route('receipts.clients.create') }}" class="btn btn-primary">+ {{ __('receipts::messages.clients.add') }}</a>
    </div>

    @if ($clients->isEmpty())
        <div class="empty-state card card-pad">{{ __('receipts::messages.clients.empty') }}</div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>{{ __('receipts::messages.clients.name') }}</th>
                            <th style="text-align:right;">{{ __('receipts::messages.clients.receipts') }}</th>
                            <th style="text-align:right;">{{ __('receipts::messages.clients.allocations') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                            <tr>
                                <td style="font-weight:600;">{{ $client->name }}</td>
                                <td class="num" style="text-align:right;">{{ $client->receipts_count }}</td>
                                <td class="num" style="text-align:right;">{{ $client->allocations_count }}</td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <div class="flex gap-sm" style="justify-content:flex-end;">
                                        <a href="{{ route('receipts.clients.edit', $client) }}" class="btn btn-sm btn-ghost">{{ __('receipts::messages.clients.edit') }}</a>
                                        <form method="POST" action="{{ route('receipts.clients.destroy', $client) }}"
                                              onsubmit="return confirm(@js(__('receipts::messages.clients.delete_confirm')))">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger">{{ __('receipts::messages.clients.delete') }}</button>
                                        </form>
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
