/* Persistent My Music docked player + in-module navigation.
   Loaded on every music page; initializes once per full page load. Links
   marked data-pjax swap only #music-page's content, and the player bar
   (music::partials.player) lives outside that container — so the YouTube
   iframe is never touched and playback survives navigation between the
   Library, Playlists and Stats pages.

   Page scripts talk to it via window.MusicPlayer and these document events:
     music:trackchange {v}   a track started (v null: player closed / list mode)
     music:played {v, pc}    a play was counted
     music:rated {v, rt}     a rating changed (bar stars or MusicPlayer.rate)
     music:noembed {v}       a video turned out not embeddable
     music:shuffle {on}      shuffle was toggled */
window.MusicPlayer = window.MusicPlayer || (() => {
    'use strict';

    const bar = document.getElementById('player-bar');
    if (!bar) return null;

    const $ = (id) => document.getElementById(id);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const VIDEOS = bar.dataset.videosUrl;

    const api = async (url, method, body) => {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || data.message || res.statusText);
        return data;
    };
    const emit = (name, detail) => document.dispatchEvent(new CustomEvent(name, { detail }));

    /* ---------- State ---------- */
    let queue = [];        // copy of the rows playback started from; survives page swaps
    let videoId = null;    // current track (queue mode)
    let listId = null;     // current YouTube playlist (list mode)
    let shuffle = false;
    let ready = false, playing = false, playCounted = false;
    let wantedVideo = null, wantedList = null;
    let yt = null;

    const currentRow = () => queue.find((r) => r.v === videoId);

    /* ---------- YouTube iframe ---------- */
    function loadYtApi() {
        if (window.YT && window.YT.Player) { initPlayer(); return; }
        window.onYouTubeIframeAPIReady = initPlayer;
        const s = document.createElement('script');
        s.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(s);
    }

    function initPlayer() {
        yt = new YT.Player('yt-player', {
            width: '320', height: '180',
            playerVars: { playsinline: 1 },
            events: {
                onReady: () => {
                    ready = true;
                    if (wantedVideo) { yt.loadVideoById(wantedVideo); wantedVideo = null; }
                    else if (wantedList) { yt.loadPlaylist({ list: wantedList, listType: 'playlist' }); wantedList = null; }
                },
                onStateChange: (e) => {
                    playing = e.data === YT.PlayerState.PLAYING;
                    $('pl-toggle').textContent = playing ? '⏸' : '▶';
                    if (e.data === YT.PlayerState.ENDED && videoId) advance(1);
                },
                onError: (e) => {
                    if (listId) { if (ready) yt.nextVideo(); return; }
                    // 101/150 = embedding disabled (100 = removed) — remember and move on.
                    if ([100, 101, 150].includes(e.data)) {
                        const row = currentRow();
                        if (row && row.e) {
                            row.e = false;
                            api(`${VIDEOS}/${row.vid}/not-embeddable`, 'POST').catch(() => {});
                            emit('music:noembed', { v: row.v });
                        }
                    }
                    advance(1);
                },
            },
        });
    }

    function showBar() {
        bar.classList.remove('hidden');
        document.body.classList.add('player-open');
    }

    /* ---------- Queue playback (library rows) ---------- */
    function setQueue(rows) {
        queue = rows.map((r) => ({ ...r }));
        if (currentRow()) refreshStars();
    }

    function play(v) {
        videoId = v; listId = null;
        playCounted = false;
        showBar();
        const row = currentRow();
        const art = $('player-art');
        art.onerror = () => art.classList.add('hidden');
        art.src = `https://i.ytimg.com/vi/${v}/hqdefault.jpg`;
        art.classList.remove('hidden');
        $('player-title').textContent = row ? row.ti : '';
        $('player-sub').textContent = row ? [row.a, row.al, row.y].filter(Boolean).join(' · ') : '';
        resetScrub();
        refreshStars();
        if (ready) yt.loadVideoById(v);
        else { wantedVideo = v; loadYtApi(); }
        emit('music:trackchange', { v });
    }

    function playAll() {
        const rows = queue.filter((r) => r.e);
        if (!rows.length) return;
        play((shuffle ? rows[Math.floor(Math.random() * rows.length)] : rows[0]).v);
    }

    function advance(step) {
        if (listId) { if (ready) { step > 0 ? yt.nextVideo() : yt.previousVideo(); } return; }
        const rows = queue.filter((r) => r.e);
        if (!rows.length) return;
        const idx = rows.findIndex((r) => r.v === videoId);
        let next;
        if (shuffle && rows.length > 1) {
            do { next = rows[Math.floor(Math.random() * rows.length)]; } while (next.v === videoId);
        } else {
            next = rows[idx < 0 ? 0 : idx + step];
        }
        if (next) play(next.v);
        else if (yt && ready) yt.stopVideo();
    }

    /* ---------- Playlist playback (a whole YouTube playlist) ---------- */
    function playList(id, name) {
        if (!id) return;
        videoId = null; listId = id;
        showBar();
        $('player-art').classList.add('hidden'); // show the video itself
        $('player-title').textContent = name || '';
        $('player-sub').textContent = '';
        $('player-stars').innerHTML = '';
        resetScrub();
        if (ready) yt.loadPlaylist({ list: id, listType: 'playlist' });
        else { wantedList = id; loadYtApi(); }
        emit('music:trackchange', { v: null });
    }

    /* ---------- Ratings (docked-player stars) ---------- */
    function starsHtml(r) {
        let out = '';
        for (let n = 1; n <= 3; n++) {
            const on = (r.rt || 0) >= n;
            out += `<button type="button" class="star${on ? ' on' : ''}" data-act="rate" data-n="${n}" title="${n} ★"><svg class="star-ico" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>`;
        }
        return out;
    }

    function refreshStars() {
        const row = currentRow();
        $('player-stars').innerHTML = row ? starsHtml(row) : '';
    }

    function rate(row, n) {
        // Click the same star that's already the max → clear the rating.
        const next = (row.rt || 0) === n ? 0 : n;
        return api(`${VIDEOS}/${row.vid}/rate`, 'POST', { rating: next }).then((d) => {
            const rt = d.rating || 0;
            const q = queue.find((r) => r.v === row.v);
            if (q) q.rt = rt;
            if (videoId === row.v) refreshStars();
            emit('music:rated', { v: row.v, rt });
            return rt;
        });
    }

    $('player-stars').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-act="rate"]');
        const row = currentRow();
        if (btn && row) rate(row, +btn.dataset.n).catch(() => {});
    });

    /* ---------- Scrub bar ---------- */
    const seek = $('seek');
    let seeking = false;
    const fmtTime = (s) => {
        s = Math.max(0, Math.floor(s || 0));
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    };
    function updateSeekFill() {
        const p = +seek.max > 0 ? (+seek.value / +seek.max) * 100 : 0;
        seek.style.background = `linear-gradient(90deg, var(--accent) ${p}%, var(--panel-2) ${p}%)`;
    }
    function resetScrub() {
        seek.value = 0;
        seek.max = 0;
        $('time-now').textContent = $('time-total').textContent = '0:00';
        updateSeekFill();
    }
    seek.addEventListener('input', () => {
        seeking = true;
        $('time-now').textContent = fmtTime(+seek.value);
        updateSeekFill();
    });
    seek.addEventListener('change', () => {
        // Command the iframe directly (getDuration/getCurrentTime polling is
        // unreliable across YT API versions; the postMessage command API is not).
        const f = bar.querySelector('iframe');
        if (f) f.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'seekTo', args: [+seek.value, true] }), '*');
        seeking = false;
    });
    // Drive the bar from the player's own infoDelivery messages (they carry
    // currentTime + duration and fire ~every 250ms while playing).
    window.addEventListener('message', (e) => {
        if (typeof e.data !== 'string' || e.data[0] !== '{' || !e.data.includes('infoDelivery')) return;
        let info;
        try { info = JSON.parse(e.data).info; } catch { return; }
        if (!info || seeking) return;
        if (info.duration > 0 && +seek.max !== Math.floor(info.duration)) {
            seek.max = Math.floor(info.duration);
            $('time-total').textContent = fmtTime(info.duration);
        }
        if (typeof info.currentTime === 'number') {
            seek.value = Math.floor(info.currentTime);
            $('time-now').textContent = fmtTime(info.currentTime);
            updateSeekFill();
            maybeCountPlay(info.currentTime, info.duration);
        }
    });

    // A "play" counts once the listener passes 30s (or half of a short track) —
    // so skips and previews don't inflate the numbers. Once per track load.
    function maybeCountPlay(t, dur) {
        if (playCounted || videoId === null) return;
        const threshold = Math.min(30, (dur || 60) * 0.5);
        if (t < threshold) return;
        playCounted = true;
        const row = currentRow();
        if (!row) return;
        api(`${VIDEOS}/${row.vid}/played`, 'POST').then((d) => {
            row.pc = d.play_count;
            emit('music:played', { v: row.v, pc: d.play_count });
        }).catch(() => {});
    }

    /* ---------- Transport ---------- */
    $('pl-prev').addEventListener('click', () => advance(-1));
    $('pl-next').addEventListener('click', () => advance(1));
    $('pl-toggle').addEventListener('click', () => {
        if (!ready) return;
        playing ? yt.pauseVideo() : yt.playVideo();
    });
    $('pl-close').addEventListener('click', () => {
        if (yt && ready) yt.stopVideo();
        videoId = null; listId = null;
        bar.classList.add('hidden');
        document.body.classList.remove('player-open');
        emit('music:trackchange', { v: null });
    });

    function setShuffle(on) {
        if (shuffle === on) return;
        shuffle = on;
        $('pl-shuffle').classList.toggle('active', on);
        if (listId && ready) yt.setShuffle(on);
        emit('music:shuffle', { on });
    }
    $('pl-shuffle').addEventListener('click', () => setShuffle(!shuffle));

    document.addEventListener('keydown', (e) => {
        const el = document.activeElement;
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(el?.tagName || '');
        if (typing || (videoId === null && listId === null)) return;
        // A clicked button keeps focus; without this, space would both
        // play/pause AND re-activate that button.
        if (el?.tagName === 'BUTTON') el.blur();
        if (e.code === 'Space') { e.preventDefault(); $('pl-toggle').click(); }
        else if (e.code === 'ArrowRight') { e.preventDefault(); advance(1); }
        else if (e.code === 'ArrowLeft') { e.preventDefault(); advance(-1); }
    });

    /* ---------- In-module navigation (swap #music-page, keep this document) ---------- */
    async function visit(url, push = true) {
        const target = document.getElementById('music-page');
        let doc;
        try {
            const res = await fetch(url, { headers: { Accept: 'text/html' } });
            if (!res.ok) throw new Error(res.statusText);
            doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        } catch { location.href = url; return; }
        const incoming = doc.getElementById('music-page');
        if (!incoming || !target) { location.href = url; return; }
        if (push) history.pushState(null, '', url);
        document.title = doc.title;
        target.innerHTML = incoming.innerHTML;
        // Re-run the page's own scripts (tagged in the fetched document, where
        // they live outside the container) — appended inside it so the next
        // swap removes them along with the page.
        doc.querySelectorAll('script[data-music-page]').forEach((s) => {
            const n = document.createElement('script');
            n.textContent = s.textContent;
            target.appendChild(n);
        });
        window.scrollTo(0, 0);
    }

    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[data-pjax]');
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        e.preventDefault();
        visit(a.href);
    });
    // Every same-document history entry is a music page (leaving the module is
    // a full navigation), so Back/Forward can always be handled with a swap.
    window.addEventListener('popstate', () => visit(location.href, false));

    /* ---------- Back to top ---------- */
    const toTop = document.getElementById('to-top');
    if (toTop) {
        toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        const refreshToTop = () => toTop.classList.toggle('show', window.scrollY > 400);
        window.addEventListener('scroll', refreshToTop, { passive: true });
        refreshToTop();
    }

    /* ---------- Theme switch without a reload (keeps playback alive) ---------- */
    const themeLink = document.querySelector('.topbar a[href*="/theme/"]');
    if (themeLink) {
        // Icon shows the ACTION: in dark mode a sun (go light), in light a moon.
        const SUN = '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="5"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1.8" x2="12" y2="4.2"/><line x1="12" y1="19.8" x2="12" y2="22.2"/><line x1="1.8" y1="12" x2="4.2" y2="12"/><line x1="19.8" y1="12" x2="22.2" y2="12"/><line x1="4.5" y1="4.5" x2="6.2" y2="6.2"/><line x1="17.8" y1="17.8" x2="19.5" y2="19.5"/><line x1="4.5" y1="19.5" x2="6.2" y2="17.8"/><line x1="17.8" y1="6.2" x2="19.5" y2="4.5"/></g></svg>';
        const MOON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/></svg>';
        themeLink.addEventListener('click', (e) => {
            e.preventDefault();
            fetch(themeLink.href).catch(() => {}); // persist in the session
            const html = document.documentElement;
            const next = html.dataset.theme === 'dark' ? 'light' : 'dark';
            html.dataset.theme = next;
            themeLink.href = themeLink.href.replace(/theme\/\w+$/, 'theme/' + (next === 'dark' ? 'light' : 'dark'));
            themeLink.innerHTML = next === 'dark' ? SUN : MOON;
        });
    }

    return {
        setQueue, play, playAll, playList, rate, advance, setShuffle, visit,
        toggleShuffle: () => setShuffle(!shuffle),
        get videoId() { return videoId; },
        get shuffle() { return shuffle; },
        get active() { return videoId !== null || listId !== null; },
    };
})();
