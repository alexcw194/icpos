#!/usr/bin/env bash
set -euo pipefail

BASE="/var/www/icpos"
TAR="/tmp/release.tar.gz"
PHP="/usr/bin/php"
FPM="php8.3-fpm"
NGINX="nginx"
RELEASE="$(date +%Y%m%d%H%M%S)"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base) BASE="$2"; shift 2 ;;
    --tar) TAR="$2"; shift 2 ;;
    --php) PHP="$2"; shift 2 ;;
    --fpm) FPM="$2"; shift 2 ;;
    --nginx) NGINX="$2"; shift 2 ;;
    --release) RELEASE="$2"; shift 2 ;;
    *) echo "Unknown arg: $1"; exit 1 ;;
  esac
done

RELEASES="$BASE/releases"
SHARED="$BASE/shared"
TARGET="$RELEASES/$RELEASE"

echo "==> Deploying release $RELEASE to $BASE"
[[ -f "$TAR" ]] || { echo "Missing tar: $TAR"; exit 1; }
[[ -d "$SHARED" ]] || { echo "Missing shared dir: $SHARED"; exit 1; }
[[ -f "$SHARED/.env" ]] || { echo "Missing $SHARED/.env"; exit 1; }

mkdir -p "$TARGET"
tar -xzf "$TAR" -C "$TARGET"

cd "$TARGET"
rm -rf storage bootstrap/cache || true
ln -s "$SHARED/storage" storage
ln -s "$SHARED/bootstrap/cache" bootstrap/cache
ln -s "$SHARED/.env" .env

chown -R deploy:www-data "$TARGET" || true
chmod -R 775 storage bootstrap/cache || true

$PHP -v >/dev/null
if grep -q "^APP_KEY=\s*$" "$SHARED/.env"; then
  echo "Generating APP_KEY..."
  $PHP artisan key:generate --force
fi

$PHP artisan storage:link || true
$PHP artisan migrate --force
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

ln -sfn "$TARGET" "$BASE/current"

systemctl reload "$FPM" || systemctl restart "$FPM" || true
systemctl reload "$NGINX" || true

ls -1dt "$RELEASES"/* | tail -n +6 | xargs -r rm -rf

echo "==> Deploy OK: $RELEASE"
