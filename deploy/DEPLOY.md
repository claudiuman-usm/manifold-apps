# Deploying Manifold Apps to cPanel (no-SSH, Git) — runbook

Battle-tested against **timer.manifold.ro** (cPanel + LiteSpeed + CloudLinux, no SSH,
MySQL via phpMyAdmin, code delivered by cPanel Git™ Version Control pulling from GitHub).
Follow the phases in order. `⚠️` marks frictions we actually hit.

**Placeholders for this app:** `USER=manifold`, `APP=manifold-apps`,
`SUBDOMAIN=apps.manifold.ro`, repo `claudiuman-usm/manifold-apps`.

## Architecture
```
Mac ──git push──► GitHub ──cPanel "Update from Remote"──► /home/manifold/repositories/manifold-apps
served docroot: /home/manifold/apps.manifold.ro/public  ──(direct, or loader shim)──► boots the app
```
- `vendor/` is **committed to git** → the server needs no Composer/SSH.
- Sessions & cache use the **file** driver → no framework DB tables.
- App tables are created by hand in phpMyAdmin from `deploy/production.sql`.

## Phase 0 — Local prep (baked into this repo)
- `vendor/` is committed (`/vendor` removed from `.gitignore`).
- `deploy/env.production.txt` — production env template.
- `deploy/production.sql` — CREATE TABLEs + admin seed.
- `deploy/public-shim/` — loader shim (only if the docroot test below fails).
- `.cpanel.yml` — permission fix only (`chmod -R 775 storage bootstrap/cache`).

## Phase 1 — Git: local → GitHub
```bash
git add -A && git commit -m "…" && git push
```
⚠️ HTTPS push can fail with `could not read Username` in a non-interactive shell — run it in a real Terminal, or use an SSH remote (`git@github.com:claudiuman-usm/manifold-apps.git`).

## Phase 2 — cPanel Git™ Version Control: clone
Create → Clone: **Clone URL** `https://claudiuman-usm:TOKEN@github.com/claudiuman-usm/manifold-apps.git`
(or SSH with a Deploy key), **Repository Path** `repositories/manifold-apps`.
Then **Manage → Update from Remote** (first pull is slow — it includes `vendor/`).
⚠️ `"" is not a valid "branch"` → repo was cloned while empty; push first, then re-clone.
⚠️ "directory already contains files" → remove the old Git entry, use a fresh path.

## Phase 3 — Database (phpMyAdmin)
MySQL Databases → create DB + user + **ALL PRIVILEGES**. (Ours: `manifold_apps` / `manifold_apps`.)
phpMyAdmin → select DB → **Import** `deploy/production.sql` (first paste your bcrypt hash into the admin INSERT).
⚠️ `#1050 Table already exists` is harmless (re-run only the INSERTs if tables are empty).

## Phase 4 — .env (on the server)
File Manager → **`/home/manifold/repositories/manifold-apps/.env`** (App root, NOT public).
Copy from `deploy/env.production.txt`; set `APP_KEY` (`php artisan key:generate --show` locally),
`DB_PASSWORD`.
⚠️ `Unsupported cipher or incorrect key length` → APP_KEY missing the `base64:` prefix or has a stray space.
⚠️ 500 on DB pages but login works → wrong DB creds / user not attached; try `localhost` vs `127.0.0.1`.

## Phase 5 — Document root (+ LiteSpeed shim if needed)
**Test first:** set docroot to `/home/manifold/repositories/manifold-apps/public`, drop a
`test.txt` there, open `http://apps.manifold.ro/test.txt`.
- **Serves it** → docroot is honored. Done — remove test.txt. (Static assets work naturally.)
- ⚠️ **LiteSpeed 404 on everything (even test.txt)** → the vhost won't honor the custom docroot.
  Use the shim: set docroot to `/home/manifold/apps.manifold.ro/public`, copy
  `deploy/public-shim/index.php` + `.htaccess` there, **and copy the app's `public/css/`** into it
  (the shim serves the front controller; static files must live in the served folder).

## Phase 6 — Permissions
File Manager → in `repositories/manifold-apps`, set `storage` and `bootstrap/cache` to **775 recursive**.
⚠️ `419 Page Expired` on form submit → Laravel can't write sessions; fix perms (775 recursive).

## Phase 7 — See real errors
`.env` → `APP_DEBUG=true` → reload → read the error (or `storage/logs/laravel.log`) → fix → set back to `false`.
A **500** means PHP ran your app (app error). A **LiteSpeed 404** means the request never reached the app (Phase 5).

## Phase 8 — HTTPS
Test on `http://` first. cPanel → **SSL/TLS Status** → tick the subdomain → **Run AutoSSL**.
Once `https://` loads, set `SESSION_SECURE_COOKIE=true` in `.env`.

## Phase 9 — Go-live checklist
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set, `APP_URL=https://apps.manifold.ro`, timezone
- [ ] DB imported & admin seeded; login works
- [ ] Docroot serves the app; `storage`/`bootstrap/cache` = 775
- [ ] HTTPS issued; `SESSION_SECURE_COOKIE=true`
- [ ] Smoke test: login, a DB page, a form submit

## Phase 10 — Updates later
```bash
git add -A && git commit -m "…" && git push
```
cPanel → Git Version Control → **Update from Remote**. Schema changes ship as SQL — run the
relevant `ALTER TABLE` in phpMyAdmin by hand. If using the shim and CSS changed, re-copy `public/css/`.
