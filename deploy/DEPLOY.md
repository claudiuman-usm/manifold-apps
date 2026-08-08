# Deploying a Laravel app to cPanel (no-SSH, Git) — battle-tested runbook

Written from the real deployments of **Manifold Timer** (timer.manifold.ro) and
**Manifold Apps** (apps.manifold.ro) on **cPanel + LiteSpeed + CloudLinux, no SSH**,
MySQL via phpMyAdmin, code delivered by cPanel Git™ Version Control pulling from GitHub.

Follow the phases in order. `⚠️` = frictions we actually hit (also in the Troubleshooting table).

---

## Per-app variables (change these for each app)
| Var | This app | Meaning |
|-----|----------|---------|
| `USER` | `manifold` | cPanel user (home = `/home/manifold`) |
| `APP` | `manifold-apps` | repo / clone folder name |
| `SUBDOMAIN` | `apps.manifold.ro` | the domain + its served folder |
| `REPO` | `claudiuman-usm/manifold-apps` | GitHub repo |
| `DB` / `DBUSER` | `manifold_apps` / `manifold_apps` | MySQL database + user (cPanel-prefixed) |
| PHP CLI | `/opt/alt/php84/usr/bin/php` | CloudLinux alt-php 8.4 |

---

## Server file map (where everything lives) — THE important reference
```
/home/manifold/
├── repositories/
│   └── manifold-apps/                  ◄── THE CODE (cPanel Git clone; update via "Update from Remote")
│       ├── app/ bootstrap/ config/ routes/ resources/ lang/ database/ tests/
│       ├── vendor/                     ◄── COMMITTED to git (no Composer on the server)
│       ├── public/                     ◄── the app's REAL public (index.php, css/app.css, .htaccess)
│       │                                   NOT served directly — LiteSpeed won't honor this docroot
│       ├── deploy/                     ◄── DEPLOY.md, production.sql, env.production.txt, public-shim/
│       ├── storage/  bootstrap/cache/  ◄── must be 775 (writable) or you get 419 errors
│       └── .env                        ◄── PRODUCTION ENV (secrets). App root, NEVER web-served.
│
└── apps.manifold.ro/                   ◄── the subdomain folder LiteSpeed actually serves
    └── public/                         ◄── DOCUMENT ROOT (set in cPanel → Domains)
        ├── index.php                   ◄── the SHIM (boots ../../repositories/manifold-apps)
        ├── .htaccess                   ◄── stock Laravel public/.htaccess
        └── css/                        ◄── COPY of the app's static assets (see Phase 5 note)
```
- **`.env`** → `/home/manifold/repositories/manifold-apps/.env` (with the code, above web root).
- **Docroot** → `/home/manifold/apps.manifold.ro/public` (shim). The repo's own `public/` is *not* the docroot on this host.
- **`test.txt`** (Phase 5 probe) → `/home/manifold/repositories/manifold-apps/public/test.txt`; delete after testing.

---

## Architecture
```
Mac ──git push──► GitHub ──cPanel "Update from Remote"──► /home/USER/repositories/APP (code + vendor)
                                                                      ▲ shim boots it
served docroot: /home/USER/SUBDOMAIN/public/index.php (shim) ─────────┘
```
- `vendor/` committed → no Composer/SSH on the server.
- Sessions & cache use the **file** driver → no framework DB tables.
- App tables created by hand in phpMyAdmin from `deploy/production.sql`.

---

## Phase 0 — Local prep (baked into this repo)
- **Commit `vendor/`** — remove `/vendor` from `.gitignore` so it ships. Also ignore local-only artefacts:
  ```gitignore
  /database/*.sqlite
  # (do NOT ignore /vendor — we ship it)
  ```
- ⚠️ **Front-end build assets:** if the app uses Vite/`npm run build`, there is **no npm on the server** — build locally and **commit `public/build/`** (remove it from `.gitignore`), or the assets won't exist. (Manifold Apps uses hand-written CSS, so no build step.)
- **`deploy/env.production.txt`** — production env template (no secrets).
- **`deploy/production.sql`** — `CREATE TABLE`s + admin seed.
  - ⚠️ **Generate it reliably:** hand-writing DDL is error-prone. Best: run the migrations into a *local MySQL* once and `mysqldump --no-data DB > deploy/production.sql` (then append the seed `INSERT`s). Or `php artisan schema:dump` on a MySQL connection.
- **`deploy/public-shim/`** — `index.php` (loader shim) + stock `.htaccess`.
- **`.cpanel.yml`** — permissions only: `- /bin/chmod -R 775 storage bootstrap/cache`.

## Phase 1 — Git: local → GitHub
```bash
git add -A && git commit -m "…" && git push
```
⚠️ HTTPS push fails with `could not read Username for 'https://github.com'` in a non-interactive shell (e.g. Claude Code's `!`). Run it in a real Terminal, or use an SSH remote:
`git remote set-url origin git@github.com:USERNAME/REPO.git` (verify: `ssh -T git@github.com`).
⚠️ GitHub still shows the "empty repo" page after a push → just cached. Confirm with `git ls-remote origin`, hard-refresh.

## Phase 2 — cPanel Git™ Version Control: clone onto the server
Create → Clone a Repository:
- **Clone URL:** private repo over HTTPS with a token → `https://USERNAME:TOKEN@github.com/REPO.git`
  (or SSH `git@github.com:REPO.git` + add cPanel's key as a GitHub **Deploy key**).
- **Repository Path:** `repositories/APP`  → `/home/USER/repositories/APP`
- **Manage → Update from Remote** pulls everything incl. `vendor/`. First pull is slow (thousands of files) — normal.

⚠️ `"" is not a valid "branch"` → cloned while the repo was still empty. Push first, then re-clone.
⚠️ "directory already contains files" / folder keeps reappearing → an old Git entry still owns it. Remove all Git entries for it, then clone into a clean path.

## Phase 3 — Database (phpMyAdmin, by hand)
1. cPanel → **MySQL® Databases** → create DB + user (strong pw) → **Add user to DB → ALL PRIVILEGES**. Note the prefixed names.
2. Generate the admin bcrypt hash locally and paste it into `production.sql`:
   ```bash
   php -r "echo password_hash('YOUR_ADMIN_PASSWORD', PASSWORD_BCRYPT, ['cost' => 12]), PHP_EOL;"
   ```
3. cPanel → **phpMyAdmin** → select the DB → **Import** `deploy/production.sql` (or SQL tab → paste → Go).
4. Verify: `SELECT COUNT(*) FROM users;` → 1.

⚠️ `#1050 Table already exists` is harmless (import stops at the first existing CREATE). Our SQL uses `CREATE TABLE IF NOT EXISTS` + `INSERT IGNORE`, so re-import is safe.

## Phase 4 — The `.env` (create on the server)
File Manager (Settings → **Show Hidden Files**) → **`/home/USER/repositories/APP/.env`** (App root, **not** public).
Copy from `deploy/env.production.txt`. Set:
- `APP_KEY` — `php artisan key:generate --show` locally, paste the **whole** value incl. `base64:`.
- `DB_DATABASE/USERNAME/PASSWORD`, `APP_URL=https://SUBDOMAIN`, `APP_TIMEZONE`.
- `SESSION_DRIVER=file`, `CACHE_STORE=file`, `SESSION_SECURE_COOKIE=false` (→ true after SSL).

⚠️ `Unsupported cipher or incorrect key length` → `APP_KEY` is wrong: missing the `base64:` prefix, a stray space/quote, or **truncated on paste**. A valid key is `base64:` + **44 chars ending in `=`** (~51 total) — copy it as one whole line. If you fixed `.env` and the error persists, the config is **cached** — see Phase 7.
⚠️ 500 on DB pages but the login page works → wrong DB creds / user not attached; try `localhost` vs `127.0.0.1`.

## Phase 5 — Document root + the LiteSpeed shim  ⚠️ (the big one)
**Probe first** — set docroot to `/home/USER/repositories/APP/public`, put `test.txt` there, open `http://SUBDOMAIN/test.txt`:
- **Serves the text** → docroot honored → you're done; static assets work; **skip the shim**.
- **404 (even for the static file)** → LiteSpeed won't rebuild the vhost for a custom docroot. **Use the shim** ↓
  *(On apps.manifold.ro this 404'd — the shim is required here.)*

**Shim setup:**
1. Docroot → `/home/USER/SUBDOMAIN/public` (the folder LiteSpeed actually serves).
2. Put `deploy/public-shim/index.php` at `/home/USER/SUBDOMAIN/public/index.php` (boots the app in `repositories/APP`).
3. Put stock `deploy/public-shim/.htaccess` there too.
4. ✅ **Static assets are served through the app** — the stylesheet is behind a Laravel route (`assets/app.css` → `AssetController`), so a request for it falls through the shim to `index.php` and is streamed from the repo's own `public/`. **Nothing to copy into the shim folder** beyond `index.php` + `.htaccess`; asset changes ship with a plain `git pull`. (For a Vite app, add a route for `public/build` the same way, or copy `build/` once.) SSL also works because `.well-known` lands in the served folder.

## Phase 6 — Permissions
File Manager → in `repositories/APP`, set **`storage` and `bootstrap/cache` to 775, recursive** (tick "Recurse into subdirectories").
⚠️ On a **no-shell** account, cPanel's **"Deploy HEAD Commit" won't run** (`.cpanel.yml` needs shell) — so set permissions via **File Manager**, not the deploy button.
⚠️ `419 Page Expired` on form submit → Laravel can't write session files → fix perms 775 recursive.

## Phase 7 — See real errors
Blank 500 hides the cause. `.env` → `APP_DEBUG=true` → reload → read the red headline (or `storage/logs/laravel.log`, writable after Phase 6) → fix → set `APP_DEBUG=false` again.
- A **500** = PHP ran your app; only the app errored.
- A **LiteSpeed 404** = the request never reached the app (docroot/shim, Phase 5).

⚠️ **Cached config silently ignores your `.env`** (this bit us hard). If `APP_DEBUG=true` but the browser still shows the *minimal* "500 Server Error" (not the detailed page), or your `.env` edits seem to have **no effect at all**, Laravel is reading a stale `bootstrap/cache/config.php`. With no shell you can't run `php artisan config:clear`, so **delete every `.php` file in `bootstrap/cache/`** via File Manager (keep `.gitignore`), then reload. In our case a stale cache held a *truncated* `APP_KEY` + `debug=false`, so no `.env` fix could take until the cache was deleted.

## Phase 8 — HTTPS
Test on `http://` first (before a cert, `https://` may 404 on a default vhost).
cPanel → **SSL/TLS Status** → tick SUBDOMAIN → **Run AutoSSL** → wait. Then `.env` → `SESSION_SECURE_COOKIE=true`.

## Phase 9 — Go-live checklist
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` (with `base64:`), `APP_URL=https://…`, `APP_TIMEZONE`
- [ ] DB imported & admin seeded; login works
- [ ] Docroot serves the app (shim if needed); assets load; `storage`/`bootstrap/cache` = 775
- [ ] HTTPS issued; `SESSION_SECURE_COOKIE=true`
- [ ] Changed default passwords/secrets from placeholders
- [ ] Smoke test: login, a DB-backed page, a form submit

## Phase 10 — Deploying updates + rollback
**Update = pull + (migrate if schema changed). Nothing else.**
```bash
git add -A && git commit -m "…" && git push      # or: git ship "…"
```
Then in cPanel: **Git Version Control → Update from Remote**. That's it for code, views, and CSS — the stylesheet is served through the app (Phase 5 step 4), so no asset copying. If the change added migrations, also hit the migrate endpoint below. `.env` secrets (new API keys, etc.) are the only thing you add by hand, and only once.

**Schema changes (no shell) — token-guarded migrate endpoint:**
1. Add the Laravel migration file locally, `git ship`, then **Update from Remote**.
2. Hit `https://SUBDOMAIN/_deploy/migrate?token=DEPLOY_TOKEN` (add `&seed=1` to also run the seeder).
   Requires `DEPLOY_TOKEN` in `.env`; returns 404 without the correct token. Only ever runs `migrate`/`db:seed`.
   ⚠️ The token rides in the URL (may hit access logs) — use a long random value and rotate if exposed.
   *(Hand `ALTER TABLE` in phpMyAdmin still works as a fallback.)*

**⚠️ Backup / rollback (do before risky changes):**
- **DB:** phpMyAdmin → Export the database first.
- **Rollback:** `git revert` (or reset) + push → "Update from Remote"; restore the DB export if a migration/ALTER was applied.

## Automation — hands-off local push
So you never fumble git. Two layers:

**1. `git ship` — one-command add + commit + push** (global alias, works in any repo, uses that repo's own remote):
```bash
git config --global alias.ship '!f(){ git add -A && git commit -m "${1:-update}" && git push; }; f'
# then, from any app folder:
git ship "what changed"
```

**2. Auto-push on every commit** — so even an editor commit / plain `git commit` pushes itself; you never run `git push`.
- **Per-repo** — create `.git/hooks/post-commit` (then `chmod +x`):
  ```sh
  #!/bin/sh
  # Auto-push after every commit. Delete this file to disable.
  ( git push >/tmp/manifold-autopush.log 2>&1 & )
  echo "→ auto-pushing to GitHub in the background"
  ```
- **Global (all apps on this Mac)** — one hooks dir for every repo:
  ```bash
  mkdir -p ~/.git-hooks
  printf '#!/bin/sh\n( git push >/tmp/autopush.log 2>&1 & )\n' > ~/.git-hooks/post-commit
  chmod +x ~/.git-hooks/post-commit
  git config --global core.hooksPath ~/.git-hooks
  ```
- Tradeoff: **every** commit pushes (fine for solo apps — WIP lands on GitHub). Disable by deleting the hook (or `git config --global --unset core.hooksPath`). Push failures log to `/tmp/…autopush.log`.

**Result — the deploy loop is now:** commit (auto-pushes) → cPanel **Update from Remote** (one click). Fully automating the server pull (push → live, no click) would need a GitHub→cPanel webhook or a cPanel API token — fiddly on this no-SSH host, so the single click stays for now.

## For future apps — extra gotchas to plan for
- **User uploads / `storage:link`:** no shell means `php artisan storage:link` can't run. For apps with uploads (receipts, migraine photos): create the symlink via a one-off cron, or serve uploads through a controller, or keep them on a non-public disk.
- **Scheduled tasks / queues:** no daemon. Use a cPanel **Cron Job** for `schedule:run` (`* * * * *`), and keep `QUEUE_CONNECTION=sync` (or a cron-driven `queue:work --once`).
- **Cron can run PHP without a shell** — handy fallback for any one-off artisan command: `/opt/alt/php84/usr/bin/php /home/USER/repositories/APP/artisan <cmd>`.

---

## Troubleshooting — frictions mapped
| Symptom | Cause | Fix |
|---|---|---|
| `could not read Username for 'https://github.com'` | HTTPS git auth in non-interactive shell | Real Terminal, or SSH remote (`ssh -T git@github.com`) |
| GitHub shows empty-repo page after push | Cached | `git ls-remote origin`; hard-refresh |
| cPanel `"" is not a valid "branch"` | Repo cloned while empty | Push first, then re-clone |
| "directory already contains files" (returns) | A Git entry still owns the path | Remove all Git entries; clone fresh path |
| **LiteSpeed 404 on every URL incl. `test.txt`** | Docroot change not honored by vhost | **Shim** in `SUBDOMAIN/public` (Phase 5) |
| CSS not updating with the shim | Stylesheet was a static copy in the served folder | Serve it through the app (`assets/app.css` route) so `git pull` updates it — no copy (Phase 5 step 4) |
| `https://` 404 but `http://` works | No SSL cert yet → default vhost | Test on http; Run AutoSSL |
| 500 `Unsupported cipher or incorrect key length` | Bad/truncated `APP_KEY` | Paste full value (`base64:`+44 chars ending `=`), no spaces |
| `.env` edits do nothing / generic 500 despite `APP_DEBUG=true` | Stale cached config | Delete `bootstrap/cache/*.php` (no shell → File Manager) |
| `419 Page Expired` on submit | Can't write session files | `storage` → 775 recursive |
| 500 on DB pages, login page fine | Wrong DB creds / user not attached | Fix `.env` DB block; `localhost` vs `127.0.0.1`; ALL PRIVILEGES |
| `#1050 Table already exists` on import | Tables already created | Harmless; re-run only INSERTs if empty |
| `Deploy HEAD Commit` refuses / does nothing | No-shell account | Do perms via File Manager; deploy code via "Update from Remote" |
| `Failed to open … vendor/autoload.php` | Wrong folder served, or vendor not pulled | Point/boot at `repositories/APP` (has vendor); Update from Remote |
