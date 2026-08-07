#!/usr/bin/env bash
#
# Deploy script run by cPanel Git Version Control (.cpanel.yml) on each
# "Deploy HEAD Commit". No SSH needed — cPanel executes this for us and the
# output shows in the deploy log. Requires .env to already exist (see setup).
#
set -euo pipefail
cd "$(dirname "$0")"

# --- Resolve a PHP 8.x binary (cPanel EA-PHP first, then whatever's on PATH).
PHP=""
for c in \
    /opt/cpanel/ea-php84/root/usr/bin/php \
    /opt/cpanel/ea-php83/root/usr/bin/php \
    /opt/cpanel/ea-php82/root/usr/bin/php \
    php; do
    if command -v "$c" >/dev/null 2>&1; then PHP="$c"; break; fi
done
if [ -z "$PHP" ]; then echo "ERROR: no PHP binary found"; exit 1; fi
echo "==> Using PHP: $PHP"
"$PHP" -v | head -1

# --- Install production dependencies (no shell/Composer required on host).
export COMPOSER_MEMORY_LIMIT=-1
echo "==> composer install (no-dev)"
"$PHP" composer.phar install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# --- Apply migrations, seed the single admin, rebuild caches.
echo "==> artisan: clear caches"
"$PHP" artisan optimize:clear
echo "==> artisan: migrate"
"$PHP" artisan migrate --force
echo "==> artisan: seed admin"
"$PHP" artisan db:seed --force
echo "==> artisan: cache config/routes/views"
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

echo "==> Deploy complete."
