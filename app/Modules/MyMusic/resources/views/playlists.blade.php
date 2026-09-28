@extends('layouts.app')
@section('title', __('music::messages.playlists.heading'))
@section('bodyClass', 'ctx-music')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('music.index') }}">{{ __('music::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ __('music::messages.playlists.heading') }}</span>
    </div>

    <div class="row-between page-head">
        <div>
            <h1>{{ __('music::messages.playlists.heading') }}</h1>
            <p>{{ __('music::messages.playlists.subheading') }}</p>
        </div>
        <a href="{{ route('music.index') }}" class="btn btn-ghost">{{ __('music::messages.playlists.back') }}</a>
    </div>

    @if ($playlists->isEmpty())
        <div class="empty-state card card-pad">{{ __('music::messages.playlists.empty') }}</div>
    @else
        <div class="playlist-list">
            @foreach ($playlists as $p)
                <div class="card card-pad playlist-card" data-id="{{ $p->id }}" data-yt="{{ $p->youtube_id }}">
                    <div class="row-between" style="align-items:flex-start;gap:14px;flex-wrap:wrap;">
                        <div style="min-width:0;">
                            <div style="font-weight:700;font-size:1.05rem;">{{ $p->name }}</div>
                            <div class="muted" style="font-size:.84rem;margin-top:4px;">
                                <span class="badge badge-sm pl-status" data-status="{{ $p->status }}">{{ __('music::messages.playlists.status.'.$p->status) }}</span>
                                <span class="pl-inserted">{{ __('music::messages.playlists.inserted', ['n' => $p->items_count]) }}</span>
                                <span class="pl-queued">· {{ __('music::messages.playlists.queued', ['n' => count($p->queue ?? [])]) }}</span>
                                @if ($p->failed_count)
                                    · {{ __('music::messages.playlists.failed', ['n' => $p->failed_count]) }}
                                @endif
                                @if ($p->last_synced_at)
                                    · {{ __('music::messages.playlists.last_synced') }} {{ $p->last_synced_at->diffForHumans() }}
                                @endif
                            </div>
                            <div class="muted pl-line" style="font-size:.84rem;min-height:1.2em;margin-top:4px;"></div>
                        </div>
                        <div class="flex gap-sm" style="flex-wrap:wrap;">
                            @if ($p->youtube_id)
                                <a class="btn btn-sm btn-ghost" target="_blank" rel="noopener"
                                   href="https://music.youtube.com/playlist?list={{ $p->youtube_id }}">↗ {{ __('music::messages.playlists.open') }}</a>
                                <button type="button" class="btn btn-sm btn-primary" data-act="play">▶ {{ __('music::messages.playlists.play') }}</button>
                            @endif
                            @if (count($p->queue ?? []) > 0)
                                <button type="button" class="btn btn-sm btn-primary" data-act="resume">{{ __('music::messages.playlists.resume') }}</button>
                            @endif
                            <button type="button" class="btn btn-sm btn-ghost" data-act="sync">{{ __('music::messages.playlists.sync') }}</button>
                            <button type="button" class="btn btn-sm btn-ghost" data-act="delete">{{ __('music::messages.playlists.delete') }}</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Docked player (playlist mode) --}}
    <div id="player-bar" class="player-bar hidden">
        <div class="player-frame"><div id="yt-player"></div></div>
        <div class="player-info">
            <div class="player-title" id="player-title"></div>
            <div class="player-sub muted" id="player-sub"></div>
        </div>
        <div class="player-controls">
            <button type="button" class="btn btn-ghost btn-sm" id="pl-prev" title="{{ __('music::messages.player.prev') }}">⏮</button>
            <button type="button" class="btn btn-primary btn-sm" id="pl-toggle" title="{{ __('music::messages.player.play') }}">⏯</button>
            <button type="button" class="btn btn-ghost btn-sm" id="pl-next" title="{{ __('music::messages.player.next') }}">⏭</button>
            <button type="button" class="btn btn-ghost btn-sm" id="pl-close" title="{{ __('music::messages.player.close') }}">✕</button>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/music-filter.js') }}?v={{ filemtime(public_path('js/music-filter.js')) }}"></script>
<script>
(() => {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const URLS = {
        base: @js(url('music/playlists')),
        data: @js(route('music.data')),
    };
    const FILTERS = @js($playlists->mapWithKeys(fn ($p) => [$p->id => $p->filter_json])->all() ?: new stdClass);
    const T = @js([
        'creating' => __('music::messages.playlists.creating'),
        'paused' => __('music::messages.playlists.paused'),
        'done' => __('music::messages.playlists.done'),
        'synced' => __('music::messages.playlists.synced'),
        'inserted' => __('music::messages.playlists.inserted'),
        'queued' => __('music::messages.playlists.queued'),
        'delete_confirm' => __('music::messages.playlists.delete_confirm'),
        'status' => __('music::messages.playlists.status'),
    ]);
    const fill = (s, vars) => s.replace(/:(\w+)/g, (_, k) => vars[k] ?? ':' + k);

    const api = async (url, method, body) => {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const err = new Error(data.error || data.message || res.statusText);
            err.data = data;
            throw err;
        }
        return data;
    };

    function paint(card, playlist) {
        card.querySelector('.pl-status').textContent = T.status[playlist.status] || playlist.status;
        card.querySelector('.pl-inserted').textContent = fill(T.inserted, { n: playlist.inserted });
        card.querySelector('.pl-queued').textContent = '· ' + fill(T.queued, { n: playlist.queued });
    }

    async function processLoop(card, id) {
        const line = card.querySelector('.pl-line');
        for (;;) {
            let d;
            try {
                d = await api(`${URLS.base}/${id}/process`, 'POST', {});
            } catch (err) {
                line.textContent = err.data?.quotaExceeded ? T.paused : err.message;
                if (err.data?.playlist) paint(card, err.data.playlist);
                return;
            }
            paint(card, d.playlist);
            line.textContent = fill(T.creating, { inserted: d.playlist.inserted, total: d.playlist.inserted + d.playlist.queued });
            if (d.playlist.queued === 0) { line.textContent = T.done; return; }
        }
    }

    document.querySelectorAll('.playlist-card').forEach((card) => {
        const id = +card.dataset.id;
        card.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-act]');
            if (!btn) return;
            const line = card.querySelector('.pl-line');

            if (btn.dataset.act === 'resume') {
                btn.disabled = true;
                await processLoop(card, id);
                btn.disabled = false;
            } else if (btn.dataset.act === 'sync') {
                btn.disabled = true;
                try {
                    const lib = await api(URLS.data, 'GET');
                    const rows = window.MusicFilter.filter(lib.tracks, FILTERS[id] || {});
                    const d = await api(`${URLS.base}/${id}/sync`, 'POST', { videoIds: rows.map((r) => r.v) });
                    paint(card, d.playlist);
                    line.textContent = fill(T.synced, { n: d.added });
                    if (d.playlist.queued > 0) await processLoop(card, id);
                } catch (err) {
                    line.textContent = err.message;
                }
                btn.disabled = false;
            } else if (btn.dataset.act === 'delete') {
                if (!confirm(T.delete_confirm)) return;
                await api(`${URLS.base}/${id}`, 'DELETE');
                card.remove();
            } else if (btn.dataset.act === 'play') {
                playPlaylist(card.dataset.yt, card.querySelector('div[style*="font-weight"]')?.textContent || '');
            }
        });
    });

    /* ---------- Playlist player ---------- */
    let yt = null, ready = false, playing = false, wanted = null;

    function playPlaylist(listId, name) {
        if (!listId) return;
        document.getElementById('player-bar').classList.remove('hidden');
        document.body.classList.add('player-open');
        document.getElementById('player-title').textContent = name;
        if (ready) { yt.loadPlaylist({ list: listId, listType: 'playlist' }); return; }
        wanted = listId;
        if (window.YT && window.YT.Player) init();
        else {
            window.onYouTubeIframeAPIReady = init;
            const s = document.createElement('script');
            s.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(s);
        }
    }

    function init() {
        yt = new YT.Player('yt-player', {
            width: '320', height: '180',
            playerVars: { playsinline: 1 },
            events: {
                onReady: () => {
                    ready = true;
                    if (wanted) { yt.loadPlaylist({ list: wanted, listType: 'playlist' }); wanted = null; }
                },
                onStateChange: (e) => {
                    playing = e.data === YT.PlayerState.PLAYING;
                    document.getElementById('pl-toggle').textContent = playing ? '⏸' : '▶';
                },
                onError: () => { if (ready) yt.nextVideo(); },
            },
        });
    }

    document.getElementById('pl-prev').addEventListener('click', () => ready && yt.previousVideo());
    document.getElementById('pl-next').addEventListener('click', () => ready && yt.nextVideo());
    document.getElementById('pl-toggle').addEventListener('click', () => ready && (playing ? yt.pauseVideo() : yt.playVideo()));
    document.getElementById('pl-close').addEventListener('click', () => {
        if (ready) yt.stopVideo();
        document.getElementById('player-bar').classList.add('hidden');
        document.body.classList.remove('player-open');
    });
})();
</script>
@endpush
