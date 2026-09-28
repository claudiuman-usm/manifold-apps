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
                <span class="stars-static">{{ str_repeat('★', (int) $r['rating']).str_repeat('☆', 3 - (int) $r['rating']) }}</span>
            @else
                {{ $r['last'] ?: __('music::messages.stats.never') }}
            @endif
        </div>
    </div>
@empty
    <p class="muted">—</p>
@endforelse
