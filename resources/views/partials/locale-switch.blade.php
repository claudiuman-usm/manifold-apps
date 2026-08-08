@php($locales = config('app.available_locales'))
@if (count($locales) > 1)
    <span class="locale-switch">
        @foreach ($locales as $loc)
            <a href="{{ route('locale.switch', $loc) }}"
               class="{{ app()->getLocale() === $loc ? 'active' : '' }}">{{ $loc }}</a>
        @endforeach
    </span>
@endif
