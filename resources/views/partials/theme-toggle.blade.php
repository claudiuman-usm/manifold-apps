@php($current = session('theme', 'light'))
<a href="{{ route('theme.switch', $current === 'dark' ? 'light' : 'dark') }}"
   class="icon-btn"
   title="{{ $current === 'dark' ? 'Switch to light mode' : 'Switch to dark mode' }}"
   aria-label="{{ $current === 'dark' ? 'Switch to light mode' : 'Switch to dark mode' }}">
    @if ($current === 'dark')
        {{-- In dark mode the action is "go light" → solid sun --}}
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <circle cx="12" cy="12" r="5"/>
            <g stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="12" y1="1.8" x2="12" y2="4.2"/><line x1="12" y1="19.8" x2="12" y2="22.2"/>
                <line x1="1.8" y1="12" x2="4.2" y2="12"/><line x1="19.8" y1="12" x2="22.2" y2="12"/>
                <line x1="4.5" y1="4.5" x2="6.2" y2="6.2"/><line x1="17.8" y1="17.8" x2="19.5" y2="19.5"/>
                <line x1="4.5" y1="19.5" x2="6.2" y2="17.8"/><line x1="17.8" y1="6.2" x2="19.5" y2="4.5"/>
            </g>
        </svg>
    @else
        {{-- In light mode the action is "go dark" → solid moon --}}
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/>
        </svg>
    @endif
</a>
