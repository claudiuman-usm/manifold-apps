@extends('layouts.app')

@section('title', __('hub.dashboard.title'))
@section('bodyClass', 'ctx-hub')

@section('content')
    <div class="page-head">
        <div class="greeting">{{ __('hub.dashboard.hello') }}</div>
        <h1>{{ __('hub.dashboard.heading') }}</h1>
        <p>{{ __('hub.dashboard.subheading') }}</p>
    </div>

    @if ($modules->isEmpty())
        <div class="empty-state card card-pad">{{ __('hub.dashboard.empty') }}</div>
    @else
        <div class="tools-grid">
            @foreach ($modules as $module)
                <div class="tool-card tool-card--{{ $module->color() }}">
                    @if ($module->url())
                        <a href="{{ $module->url() }}" class="card-link"
                           aria-label="{{ __('hub.dashboard.open') }} {{ $module->name() }}"></a>
                        <span class="arrow" aria-hidden="true">→</span>
                    @endif

                    <h3>{{ $module->name() }}</h3>
                    <p>{{ $module->description() }}</p>

                    @if ($module->shortcuts())
                        <div class="shortcuts">
                            @foreach ($module->shortcuts() as $shortcut)
                                <a href="{{ route($shortcut['route'], $shortcut['params'] ?? []) }}"
                                   class="btn btn-ghost btn-sm">
                                    {{ is_array($shortcut['label'])
                                        ? ($shortcut['label'][app()->getLocale()] ?? reset($shortcut['label']))
                                        : $shortcut['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
