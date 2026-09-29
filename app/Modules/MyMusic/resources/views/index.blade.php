@extends('layouts.app')
@section('title', __('music::messages.title'))
@section('bodyClass', 'ctx-music')

@section('content')
    {{-- Everything inside #music-page is swapped by music-player.js on
         data-pjax navigation; the docked player below it survives. --}}
    <div id="music-page">
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <span>{{ __('music::messages.title') }}</span>
    </div>

    <div class="row-between page-head">
        <div>
            <h1>{{ __('music::messages.index.heading') }}</h1>
            <p>
                {{ __('music::messages.index.subheading') }}
                @if ($configured && $token)
                    <span class="muted" id="row-count" style="font-size:.85rem;"></span>
                @endif
            </p>
        </div>
    </div>

    @if (! $configured)
        <div class="card card-pad">
            <h2 style="margin-top:0;">{{ __('music::messages.connect.heading') }}</h2>
            <p class="muted">{{ __('music::messages.connect.not_configured') }}</p>
        </div>
    @elseif (! $token)
        <div class="card card-pad" style="max-width:560px;">
            <h2 style="margin-top:0;">{{ __('music::messages.connect.heading') }}</h2>
            <p class="muted">{{ __('music::messages.connect.hint') }}</p>
            <a href="{{ route('music.oauth.redirect') }}" class="btn btn-primary">
                {{ __('music::messages.connect.button') }}
            </a>
        </div>
    @else
        <div class="music-layout">
            {{-- Sidebar: islands of search, filters, actions, then sync/enrich status --}}
            <aside class="music-side">
                {{-- Actions + search + filters stay pinned while the table scrolls --}}
                <div class="side-sticky">
                <div class="card card-pad island">
                    <div class="island-title">{{ __('music::messages.side.actions') }}</div>
                    <div class="island-stack">
                        <button type="button" id="play-all" class="btn btn-primary">▶ {{ __('music::messages.library.play_all') }}</button>
                        <button type="button" id="shuffle-btn" class="btn btn-ghost">⤨ {{ __('music::messages.library.shuffle') }}</button>
                        <button type="button" id="create-playlist" class="btn btn-ghost">＋ {{ __('music::messages.playlists.create') }}</button>
                        <a href="{{ route('music.playlists.index') }}" data-pjax class="btn btn-ghost">{{ __('music::messages.playlists.view_all') }}</a>
                        <a href="{{ route('music.stats') }}" data-pjax class="btn btn-ghost">{{ __('music::messages.stats.nav') }}</a>
                    </div>
                </div>

                <div class="card card-pad island">
                    <div class="island-title">{{ __('music::messages.side.search') }}</div>
                    <input type="text" id="f-q" class="input" placeholder="{{ __('music::messages.library.search') }}">
                </div>

                <div class="card card-pad island">
                    <div class="island-title">{{ __('music::messages.side.filters') }}</div>
                    <div class="island-stack">
                        <div class="ms" id="ms-artist" data-label="{{ __('music::messages.library.artist') }}"></div>
                        <div class="ms" id="ms-genre" data-label="{{ __('music::messages.library.genre') }}"></div>
                        <div class="flex gap-sm">
                            <input type="number" id="f-y0" class="input" placeholder="{{ __('music::messages.library.year_from') }}" min="1900" max="2100">
                            <input type="number" id="f-y1" class="input" placeholder="{{ __('music::messages.library.year_to') }}" min="1900" max="2100">
                        </div>
                        <select id="f-status" class="select">
                            <option value="">{{ __('music::messages.library.all_statuses') }}</option>
                            @foreach (['ok', 'pending', 'not_found', 'manual', 'skipped'] as $s)
                                <option value="{{ $s }}">{{ __('music::messages.status_labels.'.$s) }}</option>
                            @endforeach
                        </select>
                        <select id="f-mus" class="select">
                            <option value="music">{{ __('music::messages.library.music_only') }}</option>
                            <option value="all">{{ __('music::messages.library.everything') }}</option>
                            <option value="non">{{ __('music::messages.library.non_music') }}</option>
                        </select>
                        <select id="f-rate" class="select">
                            <option value="">{{ __('music::messages.library.any_rating') }}</option>
                            <option value="rated">{{ __('music::messages.library.rated') }}</option>
                            <option value="3">★★★</option>
                            <option value="2">★★+</option>
                            <option value="1">★+</option>
                            <option value="unrated">{{ __('music::messages.library.unrated') }}</option>
                            <option value="unplayed">{{ __('music::messages.library.unplayed') }}</option>
                        </select>
                        <button type="button" id="clear-filters" class="btn btn-sm btn-ghost">{{ __('music::messages.library.clear') }}</button>
                    </div>
                </div>
                </div> {{-- /.side-sticky --}}

                <div class="card card-pad island">
                    <div class="island-title">{{ __('music::messages.side.status') }}</div>
                    <div class="muted island-line">
                        {{ __('music::messages.connect.connected_as') }} <strong>{{ $token->account_name ?: 'YouTube' }}</strong>
                    </div>
                    <div class="island-line">
                        <span class="stat-inline" id="video-count">{{ number_format($videoCount) }}</span>
                        <span class="muted">{{ __('music::messages.index.videos') }}</span>
                    </div>
                    <div class="muted island-line" style="font-size:.8rem;">
                        {{ __('music::messages.index.last_sync') }}:
                        {{ $lastFetchedAt ? \Illuminate\Support\Carbon::parse($lastFetchedAt)->diffForHumans() : __('music::messages.index.never') }}
                    </div>
                    <div class="island-line">
                        <span class="muted" style="font-size:.8rem;">{{ __('music::messages.index.quota') }}:</span>
                        <span class="num" id="quota-today">{{ number_format($quotaToday) }}</span><span class="muted"> / {{ number_format($quotaBudget) }}</span>
                    </div>
                    <div class="enrich-bar"><span id="enrich-fill"></span></div>
                    <div class="muted island-line" style="font-size:.8rem;" id="enrich-text"></div>
                    <div class="island-stack" style="margin-top:10px;">
                        <button type="button" id="sync-btn" class="btn btn-sm btn-primary">{{ __('music::messages.index.sync') }}</button>
                        <button type="button" id="enrich-btn" class="btn btn-sm btn-ghost">{{ __('music::messages.enrich.run') }}</button>
                        <form method="POST" action="{{ route('music.oauth.disconnect') }}"
                              onsubmit="return confirm(@js(__('music::messages.connect.disconnect_confirm')));">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-ghost" style="width:100%;">{{ __('music::messages.connect.disconnect') }}</button>
                        </form>
                    </div>
                    <div id="status-line" class="muted" style="font-size:.8rem;min-height:1.2em;margin-top:8px;"></div>
                </div>
            </aside>

            {{-- Main: the table, as visible as possible --}}
            <main class="music-main">
                @if ($videoCount === 0)
                    <div class="empty-state card card-pad" id="library-empty">{{ __('music::messages.library.none') }}</div>
                @endif

                <div id="library" @class(['hidden' => $videoCount === 0])>
                    <div class="card table-wrap">
                        <table class="music-table" id="track-table">
                            <thead>
                                <tr>
                                    <th data-sort="p" class="num-col">#</th>
                                    <th></th>
                                    <th data-sort="a">{{ __('music::messages.columns.artist') }}</th>
                                    <th data-sort="ti">{{ __('music::messages.columns.title') }}</th>
                                    <th data-sort="al">{{ __('music::messages.columns.album') }}</th>
                                    <th data-sort="y" class="num-col">{{ __('music::messages.columns.year') }}</th>
                                    <th class="col-genres">{{ __('music::messages.columns.genres') }}</th>
                                    <th data-sort="rt" class="cell-stars">{{ __('music::messages.columns.rating') }}</th>
                                    <th data-sort="pc" class="num-col col-plays">{{ __('music::messages.columns.plays') }}</th>
                                    <th data-sort="c" class="col-channel">{{ __('music::messages.columns.channel') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <div class="empty-state hidden" id="table-empty" style="padding:32px;">{{ __('music::messages.library.empty') }}</div>
                    </div>
                </div>
            </main>
        </div>

        {{-- Create-playlist dialog --}}
        <dialog id="playlist-dialog" class="music-dialog">
            <form id="playlist-form" method="dialog" class="card-pad">
                <h3 style="margin-top:0;">{{ __('music::messages.playlists.create') }}</h3>
                <label class="field-label">{{ __('music::messages.playlists.name') }}
                    <input type="text" id="p-name" class="input" required maxlength="150"></label>
                <p class="muted" style="font-size:.88rem;" id="p-estimate"></p>
                <p class="muted hidden" id="p-warn" style="color:var(--warning);font-size:.85rem;"></p>
                <div class="enrich-bar hidden" id="p-bar"><span id="p-fill"></span></div>
                <p class="muted" style="font-size:.85rem;min-height:1.2em;" id="p-status"></p>
                <div class="flex gap-sm" style="justify-content:flex-end;margin-top:14px;">
                    <button type="button" class="btn btn-ghost" id="p-cancel">{{ __('music::messages.edit.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="p-submit">{{ __('music::messages.playlists.create') }}</button>
                </div>
            </form>
        </dialog>

        {{-- Edit dialog --}}
        <dialog id="edit-dialog" class="music-dialog">
            <form id="edit-form" method="dialog" class="card-pad">
                <h3 style="margin-top:0;">{{ __('music::messages.edit.heading') }}</h3>
                <input type="hidden" id="e-track">
                <label class="field-label">{{ __('music::messages.edit.artist') }}
                    <input type="text" id="e-artist" class="input"></label>
                <label class="field-label">{{ __('music::messages.edit.track_title') }}
                    <input type="text" id="e-title" class="input" required></label>
                <div class="flex gap-sm">
                    <label class="field-label" style="flex:1;">{{ __('music::messages.edit.album') }}
                        <input type="text" id="e-album" class="input"></label>
                    <label class="field-label" style="width:110px;">{{ __('music::messages.edit.year') }}
                        <input type="number" id="e-year" class="input" min="1900" max="2100"></label>
                </div>
                <label class="field-label">{{ __('music::messages.edit.genres') }}
                    <input type="text" id="e-genres" class="input"></label>
                <div class="muted hidden" id="edit-error" style="color:var(--danger);font-size:.85rem;">{{ __('music::messages.edit.failed') }}</div>
                <div class="flex gap-sm" style="justify-content:flex-end;margin-top:14px;">
                    <button type="button" class="btn btn-ghost" id="edit-cancel">{{ __('music::messages.edit.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('music::messages.edit.save') }}</button>
                </div>
            </form>
        </dialog>
    @endif
    </div> {{-- /#music-page --}}

    @if ($configured && $token)
        @include('music::partials.player')
    @endif
@endsection

@push('scripts')
@if ($configured && $token)
<script src="{{ route('assets.music-filter') }}?v={{ filemtime(public_path('js/music-filter.js')) }}"></script>
<script src="{{ route('assets.music-player') }}?v={{ filemtime(public_path('js/music-player.js')) }}"></script>
<script data-music-page>
(() => {
    'use strict';

    // Re-executed on every in-module (data-pjax) visit: abort the previous
    // page script's document-level listeners before binding new ones.
    window.musicPageAbort?.abort();
    const SIG = (window.musicPageAbort = new AbortController()).signal;
    const MP = window.MusicPlayer;

    let DATA = @js($tracks);
    let PROGRESS = @js($enrichProgress);
    const T = @js([
        'syncing' => __('music::messages.index.syncing'),
        'sync_progress' => __('music::messages.index.sync_progress'),
        'sync_done' => __('music::messages.index.sync_done'),
        'sync_failed' => __('music::messages.index.sync_failed'),
        'enrich_progress' => __('music::messages.enrich.progress'),
        'enrich_running' => __('music::messages.enrich.running'),
        'enrich_failed' => __('music::messages.enrich.failed'),
        'count' => __('music::messages.library.count'),
        'no_embed' => __('music::messages.library.no_embed'),
        'non_music_marked' => __('music::messages.library.non_music_marked'),
        'search_list' => __('music::messages.library.search_list'),
        'play' => __('music::messages.actions.play'),
        'open' => __('music::messages.actions.open'),
        'edit' => __('music::messages.actions.edit'),
        'restore' => __('music::messages.actions.restore'),
        'toggle' => __('music::messages.actions.toggle'),
        'status' => __('music::messages.status_labels'),
        'pl_tracks' => __('music::messages.playlists.tracks'),
        'pl_estimate' => __('music::messages.playlists.estimate'),
        'pl_warn' => __('music::messages.playlists.estimate_warn'),
        'pl_creating' => __('music::messages.playlists.creating'),
        'pl_paused' => __('music::messages.playlists.paused'),
        'pl_done' => __('music::messages.playlists.done'),
    ]);
    const QUOTA_BUDGET = @js((int) config('music.daily_quota', 10000));
    let quotaToday = @js($quotaToday);
    const URLS = {
        sync: @js(route('music.sync')),
        enrich: @js(route('music.enrich')),
        data: @js(route('music.data')),
        tracks: @js(url('music/tracks')),
        videos: @js(url('music/videos')),
        playlists: @js(route('music.playlists.store')),
        playlistsPage: @js(route('music.playlists.index')),
    };
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
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

    /* ---------- Filter state <-> URL ---------- */
    const params = new URLSearchParams(location.search);
    const decodeList = (v) => new Set((v || '').split('~').filter(Boolean).map(decodeURIComponent));
    const state = {
        q: params.get('q') || '',
        artists: decodeList(params.get('artists')),
        genres: decodeList(params.get('genres')),
        y0: params.get('y0') || '',
        y1: params.get('y1') || '',
        status: params.get('status') || '',
        mus: params.get('mus') || 'music',
        rate: params.get('rate') || '',
        sort: params.get('sort') || 'p',
        dir: params.get('dir') || 'asc',
    };
    function syncUrl() {
        const p = new URLSearchParams();
        if (state.q) p.set('q', state.q);
        if (state.artists.size) p.set('artists', [...state.artists].map(encodeURIComponent).join('~'));
        if (state.genres.size) p.set('genres', [...state.genres].map(encodeURIComponent).join('~'));
        if (state.y0) p.set('y0', state.y0);
        if (state.y1) p.set('y1', state.y1);
        if (state.status) p.set('status', state.status);
        if (state.mus !== 'music') p.set('mus', state.mus);
        if (state.rate) p.set('rate', state.rate);
        if (state.sort !== 'p' || state.dir !== 'asc') { p.set('sort', state.sort); p.set('dir', state.dir); }
        if (MP.shuffle) p.set('shuffle', '1');
        history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : ''));
    }

    /* ---------- Filtering + sorting (shared with the playlists page) ---------- */
    const filtered = () => window.MusicFilter.filter(DATA, state);

    /* ---------- Table rendering ---------- */
    let currentRows = [];
    const tbody = document.querySelector('#track-table tbody');

    function starsHtml(r) {
        let out = '';
        for (let n = 1; n <= 3; n++) {
            const on = (r.rt || 0) >= n;
            out += `<button type="button" class="star${on ? ' on' : ''}" data-act="rate" data-n="${n}" title="${n} ★"><svg class="star-ico" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>`;
        }
        return out;
    }

    function rowHtml(r, i) {
        const genres = (r.g || []).slice(0, 2).map((g) => `<span class="badge badge-sm">${esc(g)}</span>`).join(' ');
        const playingCls = MP.videoId === r.v ? ' playing' : '';
        const noEmbed = r.e ? '' : ` <span class="badge badge-warning badge-sm">${T.no_embed}</span>`;
        return `<tr data-i="${i}" data-v="${esc(r.v)}" class="${r.m ? '' : 'row-nonmusic'}${playingCls}">
            <td class="num-col muted">${r.p == null ? '' : r.p + 1}</td>
            <td class="cell-thumb">${r.th ? `<img src="${esc(r.th)}" loading="lazy" alt="">` : ''}</td>
            <td class="cell-clamp cell-artist" title="${esc(r.a || r.c || '')}">${esc(r.a) || `<span class="muted">${esc(r.c || '—')}</span>`}</td>
            <td class="cell-title cell-clamp" title="${esc(r.ti)}"><span class="dot dot-${esc(r.s)}" title="${esc(T.status[r.s] || r.s)}"></span>${esc(r.ti)}${noEmbed}</td>
            <td class="muted cell-clamp cell-album" title="${esc(r.al || '')}">${esc(r.al || '')}</td>
            <td class="num-col">${r.y ?? ''}</td>
            <td class="col-genres">${genres}</td>
            <td class="cell-stars">${starsHtml(r)}</td>
            <td class="num-col col-plays muted">${r.pc || ''}</td>
            <td class="muted cell-channel col-channel">${esc(r.c || '')}</td>
            <td class="cell-actions">
                <button type="button" class="rowbtn" data-act="play" title="${T.play}">▶</button>
                <a class="rowbtn" href="https://music.youtube.com/watch?v=${esc(r.v)}" target="_blank" rel="noopener" title="${T.open}">↗</a>
                <button type="button" class="rowbtn" data-act="edit" title="${T.edit}">✎</button>
                ${state.mus === 'non' ? `<button type="button" class="rowbtn" data-act="restore" title="${T.restore}">♪</button>` : ''}
            </td>
        </tr>`;
    }

    function render() {
        if (!tbody.isConnected) return; // stale async loop from a swapped-out page
        currentRows = filtered();
        MP.setQueue(currentRows);
        tbody.innerHTML = currentRows.map(rowHtml).join('');
        $('table-empty').classList.toggle('hidden', currentRows.length > 0);
        $('row-count').textContent = fill(T.count, { shown: currentRows.length, total: DATA.length });
        document.querySelectorAll('#track-table th[data-sort]').forEach((th) => {
            th.classList.toggle('sorted', th.dataset.sort === state.sort);
            th.dataset.dir = th.dataset.sort === state.sort ? state.dir : '';
        });
        syncUrl();
        renderEnrich();
    }

    function renderEnrich() {
        const total = PROGRESS.parsed - PROGRESS.skipped;
        const done = PROGRESS.ok + PROGRESS.not_found + PROGRESS.manual;
        $('enrich-fill').style.width = total ? Math.round(done / total * 100) + '%' : '0';
        $('enrich-text').textContent = fill(T.enrich_progress, { done, total, pending: PROGRESS.pending });
    }

    /* ---------- Multi-select dropdowns ---------- */
    function buildOptions() {
        const artists = new Map(), genres = new Map();
        DATA.forEach((r) => {
            if (state.mus === 'music' && !r.m) return;
            const a = r.a || '—';
            artists.set(a, (artists.get(a) || 0) + 1);
            (r.gf || []).forEach((g) => genres.set(g, (genres.get(g) || 0) + 1));
        });
        return {
            artists: [...artists.entries()].sort((a, b) => a[0].localeCompare(b[0])),
            genres: [...genres.entries()].sort((a, b) => b[1] - a[1]),
        };
    }

    function makeMultiSelect(rootId, getItems, selected) {
        const root = $(rootId);
        const label = root.dataset.label;
        root.innerHTML = `<button type="button" class="select ms-btn"></button>
            <div class="ms-panel hidden">
                <input type="text" class="input ms-search" placeholder="${T.search_list}">
                <div class="ms-list"></div>
            </div>`;
        const btn = root.querySelector('.ms-btn');
        const panel = root.querySelector('.ms-panel');
        const search = root.querySelector('.ms-search');
        const list = root.querySelector('.ms-list');

        const refreshBtn = () => {
            btn.textContent = selected.size ? `${label} (${selected.size})` : label;
            btn.classList.toggle('active', selected.size > 0);
        };
        const renderList = () => {
            const q = search.value.trim().toLowerCase();
            list.innerHTML = getItems()
                .filter(([name]) => !q || name.toLowerCase().includes(q))
                .slice(0, 400)
                .map(([name, n]) => `<label class="ms-item"><input type="checkbox" value="${esc(name)}" ${selected.has(name) ? 'checked' : ''}> <span>${esc(name)}</span> <em>${n}</em></label>`)
                .join('');
        };
        btn.addEventListener('click', () => {
            const open = panel.classList.toggle('hidden');
            if (!open) { renderList(); search.value = ''; search.focus(); }
        });
        search.addEventListener('input', renderList);
        list.addEventListener('change', (e) => {
            const v = e.target.value;
            e.target.checked ? selected.add(v) : selected.delete(v);
            refreshBtn();
            render();
        });
        document.addEventListener('click', (e) => {
            if (!root.contains(e.target)) panel.classList.add('hidden');
        }, { signal: SIG });
        refreshBtn();
        return { refreshBtn };
    }

    const opts = () => buildOptions();
    const msArtist = makeMultiSelect('ms-artist', () => opts().artists, state.artists);
    const msGenre = makeMultiSelect('ms-genre', () => opts().genres, state.genres);

    /* ---------- Filter inputs ---------- */
    $('f-q').value = state.q;
    $('f-y0').value = state.y0;
    $('f-y1').value = state.y1;
    $('f-status').value = state.status;
    $('f-mus').value = state.mus;
    $('f-rate').value = state.rate;

    $('f-q').addEventListener('input', () => { state.q = $('f-q').value; render(); });
    $('f-y0').addEventListener('input', () => { state.y0 = $('f-y0').value; render(); });
    $('f-y1').addEventListener('input', () => { state.y1 = $('f-y1').value; render(); });
    $('f-status').addEventListener('change', () => { state.status = $('f-status').value; render(); });
    $('f-mus').addEventListener('change', () => { state.mus = $('f-mus').value; render(); });
    $('f-rate').addEventListener('change', () => { state.rate = $('f-rate').value; render(); });
    $('clear-filters').addEventListener('click', () => {
        Object.assign(state, { q: '', y0: '', y1: '', status: '', mus: 'music', rate: '' });
        state.artists.clear(); state.genres.clear();
        $('f-q').value = $('f-y0').value = $('f-y1').value = '';
        $('f-status').value = ''; $('f-mus').value = 'music'; $('f-rate').value = '';
        msArtist.refreshBtn(); msGenre.refreshBtn();
        render();
    });

    document.querySelectorAll('#track-table th[data-sort]').forEach((th) => {
        th.addEventListener('click', () => {
            const k = th.dataset.sort;
            if (state.sort === k) state.dir = state.dir === 'asc' ? 'desc' : 'asc';
            else { state.sort = k; state.dir = 'asc'; }
            render();
        });
    });

    /* ---------- Player wiring (the docked player itself lives in music-player.js) ---------- */
    $('play-all').addEventListener('click', () => MP.playAll());

    const refreshShuffleBtn = () => {
        $('shuffle-btn').classList.toggle('btn-primary', MP.shuffle);
        $('shuffle-btn').classList.toggle('btn-ghost', !MP.shuffle);
    };
    $('shuffle-btn').addEventListener('click', () => MP.toggleShuffle());
    document.addEventListener('music:shuffle', () => { refreshShuffleBtn(); syncUrl(); }, { signal: SIG });
    if (params.get('shuffle') === '1') MP.setShuffle(true);
    refreshShuffleBtn();

    document.addEventListener('music:trackchange', (e) => {
        render(); // refresh row highlight
        if (!e.detail.v) return;
        const tr = tbody.querySelector(`tr[data-v="${CSS.escape(e.detail.v)}"]`);
        if (tr) tr.scrollIntoView({ block: 'center' });
    }, { signal: SIG });

    document.addEventListener('music:played', (e) => {
        const row = DATA.find((r) => r.v === e.detail.v);
        if (row) row.pc = e.detail.pc;
        const cell = tbody.querySelector(`tr[data-v="${CSS.escape(e.detail.v)}"] .col-plays`);
        if (cell) cell.textContent = e.detail.pc || '';
    }, { signal: SIG });

    document.addEventListener('music:rated', (e) => {
        const row = DATA.find((r) => r.v === e.detail.v);
        if (!row) return;
        row.rt = e.detail.rt;
        const cell = tbody.querySelector(`tr[data-v="${CSS.escape(row.v)}"] .cell-stars`);
        if (cell) cell.innerHTML = starsHtml(row);
    }, { signal: SIG });

    document.addEventListener('music:noembed', (e) => {
        const row = DATA.find((r) => r.v === e.detail.v);
        if (row) row.e = false;
    }, { signal: SIG });

    /* ---------- Row actions ---------- */
    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-act]');
        if (!btn) return;
        const tr = btn.closest('tr');
        const row = currentRows[+tr.dataset.i];
        if (!row) return;

        if (btn.dataset.act === 'play') MP.play(row.v);
        else if (btn.dataset.act === 'edit') openEdit(row);
        else if (btn.dataset.act === 'rate') MP.rate(row, +btn.dataset.n).catch(() => {});
        else if (btn.dataset.act === 'restore') {
            // Only offered in the Non-music view: move the track back to Music.
            api(`${URLS.videos}/${row.vid}/toggle-music`, 'POST').then((d) => {
                row.m = d.is_music;
                if (row.m && row.s === 'skipped') row.s = 'pending';
                render();
            }).catch(() => {});
        }
    });

    /* ---------- Edit dialog ---------- */
    const dialog = $('edit-dialog');
    let editingRow = null;

    function openEdit(row) {
        editingRow = row;
        $('e-track').value = row.t ?? '';
        $('e-artist').value = row.a || '';
        $('e-title').value = row.ti || '';
        $('e-album').value = row.al || '';
        $('e-year').value = row.y ?? '';
        $('e-genres').value = (row.g || []).join(', ');
        $('edit-error').classList.add('hidden');
        dialog.showModal();
    }

    $('edit-cancel').addEventListener('click', () => dialog.close());
    $('edit-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!editingRow || !editingRow.t) { dialog.close(); return; }
        try {
            const d = await api(`${URLS.tracks}/${editingRow.t}`, 'PUT', {
                artist: $('e-artist').value || null,
                title: $('e-title').value,
                album: $('e-album').value || null,
                year: $('e-year').value || null,
                genres: $('e-genres').value || null,
            });
            Object.assign(editingRow, {
                a: d.track.artist, ti: d.track.title, al: d.track.album,
                y: d.track.year, g: d.track.genres || [], s: d.track.enrich_status,
            });
            dialog.close();
            render();
        } catch {
            $('edit-error').classList.remove('hidden');
        }
    });

    /* ---------- Sync ---------- */
    $('sync-btn').addEventListener('click', async () => {
        const btn = $('sync-btn'), status = $('status-line');
        btn.disabled = true;
        status.textContent = T.syncing;
        let pageToken = null, inserted = 0, scanned = 0;
        try {
            for (;;) {
                const d = await api(URLS.sync, 'POST', { pageToken });
                inserted += d.inserted; scanned += d.scanned;
                $('quota-today').textContent = d.quotaToday.toLocaleString();
                $('video-count').textContent = d.total.toLocaleString();
                if (d.done) {
                    status.textContent = fill(T.sync_done, { inserted, total: d.total.toLocaleString() });
                    break;
                }
                status.textContent = fill(T.sync_progress, { scanned, inserted });
                pageToken = d.nextPageToken;
            }
            await refreshData();
        } catch (err) {
            status.textContent = fill(T.sync_failed, { message: err.message });
        } finally {
            btn.disabled = false;
        }
    });

    /* ---------- Enrichment (browser-driven chunks) ---------- */
    let enriching = false;
    $('enrich-btn').addEventListener('click', async () => {
        if (enriching) { enriching = false; return; }
        enriching = true;
        const btn = $('enrich-btn'), status = $('status-line');
        try {
            for (let n = 1; ; n++) {
                const d = await api(URLS.enrich, 'POST', {});
                PROGRESS = d.progress;
                renderEnrich();
                status.textContent = fill(T.enrich_running, { pending: PROGRESS.pending });
                // Refresh the table right after the parse pass, then periodically,
                // so results appear while the loop is still running.
                if (n === 1 || n % 10 === 0) await refreshData();
                if (!enriching || PROGRESS.pending === 0) break;
            }
            status.textContent = '';
            await refreshData();
        } catch (err) {
            status.textContent = fill(T.enrich_failed, { message: err.message });
        } finally {
            enriching = false;
        }
    });

    /* ---------- Create playlist from current filter ---------- */
    const pDialog = $('playlist-dialog');

    $('create-playlist').addEventListener('click', () => {
        const n = currentRows.length;
        if (!n) return;
        const estimate = 50 + 50 * n;
        const remaining = Math.max(0, QUOTA_BUDGET - quotaToday);
        $('p-name').value = '';
        $('p-estimate').textContent = `${fill(T.pl_tracks, { n })} — ${fill(T.pl_estimate, { units: estimate.toLocaleString(), remaining: remaining.toLocaleString() })}`;
        $('p-warn').classList.toggle('hidden', estimate <= remaining);
        $('p-warn').textContent = T.pl_warn;
        $('p-bar').classList.add('hidden');
        $('p-status').textContent = '';
        $('p-submit').disabled = false;
        pDialog.showModal();
    });
    $('p-cancel').addEventListener('click', () => pDialog.close());

    $('playlist-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const rows = currentRows;
        const total = rows.length;
        if (!total) { pDialog.close(); return; }
        $('p-submit').disabled = true;
        $('p-bar').classList.remove('hidden');

        try {
            const created = await api(URLS.playlists, 'POST', {
                name: $('p-name').value,
                filter: {
                    q: state.q, artists: [...state.artists], genres: [...state.genres],
                    y0: state.y0, y1: state.y1, status: state.status, mus: state.mus,
                    sort: state.sort, dir: state.dir,
                },
                videoIds: rows.map((r) => r.v),
            });
            await processPlaylist(created.playlist, total);
        } catch (err) {
            $('p-status').textContent = err.message;
            $('p-submit').disabled = false;
        }
    });

    async function processPlaylist(playlist, total) {
        for (;;) {
            let d;
            try {
                d = await api(`${URLS.playlists}/${playlist.id}/process`, 'POST', {});
            } catch (err) {
                $('p-status').textContent = err.data?.quotaExceeded ? T.pl_paused : err.message;
                $('p-submit').disabled = false;
                return;
            }
            playlist = d.playlist;
            quotaToday = playlist.quotaToday;
            $('quota-today').textContent = quotaToday.toLocaleString();
            $('p-fill').style.width = total ? Math.round(playlist.inserted / total * 100) + '%' : '0';
            $('p-status').textContent = fill(T.pl_creating, { inserted: playlist.inserted, total });
            if (playlist.queued === 0) {
                $('p-status').textContent = T.pl_done;
                setTimeout(() => MP.visit(URLS.playlistsPage), 800);
                return;
            }
        }
    }

    async function refreshData() {
        const d = await api(URLS.data, 'GET');
        DATA = d.tracks;
        PROGRESS = d.progress;
        $('quota-today').textContent = d.quotaToday.toLocaleString();
        $('library').classList.toggle('hidden', DATA.length === 0);
        if (DATA.length > 0) $('library-empty')?.classList.add('hidden');
        render();
    }

    render();
    if (MP.videoId) {
        const tr = tbody.querySelector(`tr[data-v="${CSS.escape(MP.videoId)}"]`);
        if (tr) tr.scrollIntoView({ block: 'center' });
    }
})();
</script>
@endif
@endpush
