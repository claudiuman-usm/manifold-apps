/* Shared My Music filter + sort. The library page uses it live; the playlists
   page re-runs a stored filter against fresh data when syncing a playlist.
   State shape: {q, artists[], genres[], y0, y1, status, mus, sort, dir}. */
window.MusicFilter = (() => {
    'use strict';

    function filter(data, s) {
        const artists = s.artists instanceof Set ? s.artists : new Set(s.artists || []);
        const genres = s.genres instanceof Set ? s.genres : new Set(s.genres || []);
        const q = (s.q || '').trim().toLowerCase();
        const y0 = s.y0 ? +s.y0 : null;
        const y1 = s.y1 ? +s.y1 : null;
        const mus = s.mus || 'music';

        const rows = data.filter((r) => {
            if (mus === 'music' && !r.m) return false;
            if (mus === 'non' && r.m) return false;
            if (s.status && r.s !== s.status) return false;
            if (s.rate) {
                const rt = r.rt || 0;
                if (s.rate === 'rated' && rt === 0) return false;
                else if (s.rate === 'unrated' && rt > 0) return false;
                else if (s.rate === 'unplayed' && (r.pc || 0) > 0) return false;
                else if (/^[123]$/.test(s.rate) && rt < +s.rate) return false;
            }
            if (artists.size && !artists.has(r.a || '—')) return false;
            if (genres.size && !(r.gf || []).some((g) => genres.has(g))) return false;
            if ((y0 || y1) && (r.y == null || (y0 && r.y < y0) || (y1 && r.y > y1))) return false;
            if (q) {
                const hay = `${r.a || ''} ${r.ti || ''} ${r.al || ''} ${r.c || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });

        const k = s.sort || 'p';
        const dir = (s.dir || 'asc') === 'desc' ? -1 : 1;
        const numeric = k === 'p' || k === 'y';
        rows.sort((x, z) => {
            const a = x[k], b = z[k];
            if (a == null && b == null) return 0;
            if (a == null) return 1; // nulls always last
            if (b == null) return -1;
            return dir * (numeric ? a - b : String(a).localeCompare(String(b), undefined, { sensitivity: 'base' }));
        });
        return rows;
    }

    return { filter };
})();
