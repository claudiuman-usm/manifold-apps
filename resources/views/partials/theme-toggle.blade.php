@php($current = session('theme', 'light'))
<a href="{{ route('theme.switch', $current === 'dark' ? 'light' : 'dark') }}"
   class="icon-btn"
   title="{{ $current === 'dark' ? 'Light mode' : 'Dark mode' }}"
   aria-label="{{ $current === 'dark' ? 'Switch to light mode' : 'Switch to dark mode' }}">
    ◐
</a>
