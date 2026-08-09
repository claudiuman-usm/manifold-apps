{{--
    Run-launch actions for a template.
    Props: $template (required), $size (btn size class, e.g. 'btn-sm'), $label (start-button text).
    If a flow is already in progress it offers Resume + Start-new (with a discard confirm);
    otherwise a single Start button.
--}}
@php($size = $size ?? '')
@php($active = $template->activeRun)
@if ($active)
    <span class="pill pill-live">{{ __('flower::messages.index.in_progress') }}</span>
    <a href="{{ route('flower.runs.show', $active) }}" class="btn btn-primary {{ $size }}">
        {{ __('flower::messages.index.resume') }}
    </a>
    <form method="POST" action="{{ route('flower.runs.start', $template) }}"
          onsubmit="return confirm(@js(__('flower::messages.index.start_new_confirm')))">
        @csrf
        <button type="submit" class="btn {{ $size }}">{{ __('flower::messages.index.start_new') }}</button>
    </form>
@else
    <form method="POST" action="{{ route('flower.runs.start', $template) }}">
        @csrf
        <button type="submit" class="btn btn-primary {{ $size }}">
            {{ $label ?? __('flower::messages.index.start_run') }}
        </button>
    </form>
@endif
