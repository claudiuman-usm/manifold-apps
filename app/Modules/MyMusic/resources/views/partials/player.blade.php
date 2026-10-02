{{-- Docked player. Lives OUTSIDE #music-page so it (and its YouTube iframe)
     survives in-module navigation; public/js/music-player.js owns it. --}}
<div id="player-bar" class="player-bar hidden" data-videos-url="{{ url('music/videos') }}" data-library-url="{{ route('music.index') }}">
    <div id="yt-frame-holder" class="player-frame">
        <div id="yt-player"></div>
        {{-- Artwork covers the video; the iframe keeps playing under it. --}}
        <img id="player-art" class="player-art hidden" alt="">
    </div>
    <div class="player-info">
        <div class="player-title" id="player-title"></div>
        <div class="player-sub muted" id="player-sub"></div>
        <div class="player-meta">
            <div class="player-stars" id="player-stars"></div>
            <div class="player-tempo hidden" id="player-tempo">
                <button type="button" data-tempo="slow" title="{{ __('music::messages.player.tempo_slow') }}">{{ __('music::messages.player.slow') }}</button>
                <button type="button" data-tempo="fast" title="{{ __('music::messages.player.tempo_fast') }}">{{ __('music::messages.player.fast') }}</button>
            </div>
        </div>
        <div class="player-scrub">
            <span class="num" id="time-now">0:00</span>
            <input type="range" id="seek" class="seek" min="0" max="0" step="1" value="0" aria-label="Seek">
            <span class="num muted" id="time-total">0:00</span>
        </div>
        <button type="button" class="player-goto hidden" id="pl-goto">{{ __('music::messages.player.go_to_song') }}</button>
    </div>
    <div class="player-controls">
        <button type="button" class="btn btn-ghost btn-sm" id="pl-prev" title="{{ __('music::messages.player.prev') }}">⏮</button>
        <button type="button" class="btn btn-primary btn-sm" id="pl-toggle" title="{{ __('music::messages.player.play') }}">⏯</button>
        <button type="button" class="btn btn-ghost btn-sm" id="pl-next" title="{{ __('music::messages.player.next') }}">⏭</button>
        <button type="button" class="btn btn-ghost btn-sm" id="pl-shuffle" title="{{ __('music::messages.player.shuffle') }}">⤨</button>
        <button type="button" class="btn btn-ghost btn-sm" id="pl-close" title="{{ __('music::messages.player.close') }}">✕</button>
    </div>
</div>

{{-- Back to top — appears after scrolling; music-player.js owns it. --}}
<button type="button" id="to-top" class="to-top" title="{{ __('music::messages.player.to_top') }}" aria-label="{{ __('music::messages.player.to_top') }}">
    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><polyline points="6 14 12 8 18 14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>
