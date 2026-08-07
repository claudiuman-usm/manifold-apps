<span class="locale-switch">
    @foreach (config('app.available_locales') as $loc)
        <a href="{{ route('locale.switch', $loc) }}"
           class="{{ app()->getLocale() === $loc ? 'active' : '' }}">{{ $loc }}</a>
    @endforeach
</span>
