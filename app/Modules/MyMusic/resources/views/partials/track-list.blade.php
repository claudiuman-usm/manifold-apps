{{-- $rows: collection of {artist,title,plays,rating,last}; $metric: plays|rating|last --}}
@forelse ($rows as $r)
    <div class="track-row">
        <div class="track-main">
            <div class="track-title">{{ $r['title'] }}</div>
            <div class="muted track-artist">{{ $r['artist'] ?: '—' }}</div>
        </div>
        <div class="track-metric muted num">
            @if ($metric === 'plays')
                {{ $r['plays'] }} {{ __('music::messages.stats.plays') }}
            @elseif ($metric === 'rating')
                <span class="stars-static">
                    @for ($n = 1; $n <= 3; $n++)
                        <svg class="star-ico{{ $n <= (int) $r['rating'] ? ' filled' : '' }}" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    @endfor
                </span>
            @else
                {{ $r['last'] ?: __('music::messages.stats.never') }}
            @endif
        </div>
    </div>
@empty
    <p class="muted">—</p>
@endforelse
