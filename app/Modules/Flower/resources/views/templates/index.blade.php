@extends('layouts.app')

@section('title', __('flower::messages.title'))
@section('bodyClass', 'ctx-flower')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <span>{{ __('flower::messages.title') }}</span>
    </div>

    <div class="row-between page-head">
        <div>
            <h1>{{ __('flower::messages.index.heading') }}</h1>
            <p>{{ __('flower::messages.index.subheading') }}</p>
        </div>
        <a href="{{ route('flower.templates.create') }}" class="btn btn-primary">
            + {{ __('flower::messages.index.new_template') }}
        </a>
    </div>

    @if ($clients->isEmpty())
        <div class="empty-state card card-pad">{{ __('flower::messages.index.empty') }}</div>
    @else
        <div class="stack">
            @foreach ($clients as $client)
                @continue($client->types->isEmpty())
                <section>
                    <h2 class="section-label">{{ $client->name }}</h2>

                    @foreach ($client->types as $type)
                        @continue($type->templates->isEmpty())
                        <div class="card card-pad" style="margin-bottom:12px;">
                            <div class="type-label">{{ $type->name }}</div>

                            <div class="table-wrap">
                                <table class="data">
                                    <tbody>
                                    @foreach ($type->templates as $template)
                                        <tr>
                                            <td style="font-weight:600;">{{ $template->name }}</td>
                                            <td class="muted">
                                                {{ trans_choice('flower::messages.index.steps_count', $template->steps_count, ['count' => $template->steps_count]) }}
                                            </td>
                                            <td class="muted">
                                                {{ trans_choice('flower::messages.index.runs_count', $template->completed_runs_count, ['count' => $template->completed_runs_count]) }}
                                            </td>
                                            <td style="text-align:right;white-space:nowrap;">
                                                <div class="flex gap-sm" style="justify-content:flex-end;align-items:center;">
                                                    @if ($template->steps_count > 0)
                                                        @include('flower::runs._launch', ['template' => $template, 'size' => 'btn-sm'])
                                                    @endif
                                                    <a href="{{ route('flower.templates.history', $template) }}" class="btn btn-sm">
                                                        {{ __('flower::messages.index.history') }}
                                                    </a>
                                                    <a href="{{ route('flower.templates.edit', $template) }}" class="btn btn-sm">
                                                        {{ __('flower::messages.index.edit') }}
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>
    @endif
@endsection
