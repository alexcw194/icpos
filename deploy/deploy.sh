#!/usr/bin/env bash
# Deploy script untuk aplikasi Laravel (capistrano-style)
# Contoh:
#   bash deploy.sh \
#     --base "/var/www/icpos" \
#     --tar "/tmp/release.tar.gz" \
#     --php "/usr/bin/php" \
#     --fpm "php8.3-fpm" \
#     --nginx "nginx" \
#     --release "$GITHUB_SHA" \
#     --web-user "www-data"

set -Eeuo pipefail
umask 022

log()  { printf "\033[1;32m==> %s\033[0m\n" "$*"; }
warn() { printf "\033[1;33m[warn] %s\033[0m\n" "$*"; }
err()  { printf "\033[1;31m[err] %s\033[0m\n" "$*" >&2; }

on_error() {
  err "Deploy gagal pada step di atas."
  if [[ -f "$SHARED_DIR/storage/logs/laravel.log" ]]; then
    warn "Tail laravel.log (20 baris terakhir):"
    tail -n 20 "$SHARED_DIR/storage/logs/laravel.log" || true
  fi
}
trap on_error ERR

# --- sudo helpers ---
maybe_sudo() {
  if command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    sudo "$@"
  else
    "$@"
  fi
}
maybe_sudo_u() { # run as specific user jika bisa
  local user="$1"; shift
  if command -v sudo >/dev/null 2>&1 && sudo -n -u "$user" true 2>/dev/null; then
    sudo -u "$user" "$@"
  else
    "$@"
  fi
}

# --- args & defaults ---
BASE=""             # mandatory
TAR_FILE=""         # mandatory
PHP_BIN="/usr/bin/php"
FPM_SVC=""
NGINX_SVC=""
RELEASE=""
KEEP="${KEEP:-5}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP=""

usage() {
  cat <<EOF
Usage: $0 --base <dir> --tar <file> [--php <bin>] [--fpm <svc>] [--nginx <svc>] [--release <id>] [--keep N] [--web-user USER]
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base) BASE="$2"; shift 2 ;;
    --tar) TAR_FILE="$2"; shift 2 ;;
    --php) PHP_BIN="$2"; shift 2 ;;
    --fpm) FPM_SVC="$2"; shift 2 ;;
    --nginx) NGINX_SVC="$2"; shift 2 ;;
    --release) RELEASE="$2"; shift 2 ;;
    --keep) KEEP="$2"; shift 2 ;;
    --web-user) WEB_USER="$2"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) err "Unknown arg: $1"; usage; exit 2 ;;
  esac
done

[[ -n "$BASE" && -n "$TAR_FILE" ]] || { usage; exit 2; }
[[ -f "$TAR_FILE" ]] || { err "Tar file not found: $TAR_FILE"; exit 2; }
[[ -x "$PHP_BIN" ]] || { err "PHP bin not executable: $PHP_BIN"; exit 2; }

WEB_GROUP="$(id -gn "$WEB_USER" 2>/dev/null || echo "$WEB_USER")"
RELEASE="${RELEASE:-$(date +%Y%m%d%H%M%S)}"

RELEASES_DIR="$BASE/releases"
SHARED_DIR="$BASE/shared"
CURRENT_LINK="$BASE/current"
RELEASE_DIR="$RELEASES_DIR/$RELEASE"

log "Deploying release $RELEASE to $BASE (web user: $WEB_USER:$WEB_GROUP)"

# --- prepare dirs (shared) ---
log "Prepare directories"
maybe_sudo install -d -m 2775 -o "$WEB_USER" -g "$WEB_GROUP" \
  "$RELEASES_DIR" \
  "$SHARED_DIR" \
  "$SHARED_DIR/storage" \
  "$SHARED_DIR/storage/app/public" \
  "$SHARED_DIR/storage/framework/cache/data" \
  "$SHARED_DIR/storage/framework/sessions" \
  "$SHARED_DIR/storage/framework/views" \
  "$SHARED_DIR/storage/logs" \
  "$SHARED_DIR/bootstrap/cache"

# pastikan file log ada
maybe_sudo touch "$SHARED_DIR/storage/logs/laravel.log"
maybe_sudo chown -R "$WEB_USER:$WEB_GROUP" "$SHARED_DIR"
maybe_sudo chmod -R ug+rwX "$SHARED_DIR"

# --- extract release ---
log "Extracting archive"
maybe_sudo install -d -m 0755 "$RELEASE_DIR"
tar --no-same-owner -xzf "$TAR_FILE" -C "$RELEASE_DIR"

# --- link shared resources ---
log "Linking shared resources"
if [[ -f "$SHARED_DIR/.env" ]]; then
  ln -sfn "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
else
  warn "shared .env tidak ditemukan di $SHARED_DIR/.env"
fi

# release bootstrap/cache harus ada & writeable oleh web user
maybe_sudo install -d -m 2775 -o "$WEB_USER" -g "$WEB_GROUP" "$RELEASE_DIR/bootstrap/cache"

# storage di release -> shared/storage
rm -rf "$RELEASE_DIR/storage" || true
ln -sfn "$SHARED_DIR/storage" "$RELEASE_DIR/storage"

# izin codebase: cukup read/exec; write ada di shared
chmod -R u=rwX,go=rX "$RELEASE_DIR" || true

# --- artisan helpers ---
run_artisan() {
  local cmd=( "$PHP_BIN" artisan "$@" --no-interaction )
  ( cd "$RELEASE_DIR" && maybe_sudo_u "$WEB_USER" "${cmd[@]}" )
}

# --- warm caches & migrate ---
log "Running artisan tasks"
if ( cd "$RELEASE_DIR" && "$PHP_BIN" artisan --version >/dev/null 2>&1 ); then
  # Clear dulu untuk mencegah cache usang
  run_artisan config:clear      || warn "config:clear gagal"
  run_artisan route:clear       || warn "route:clear gagal"
  run_artisan view:clear        || warn "view:clear gagal"
  run_artisan cache:clear       || warn "cache:clear gagal"

  run_artisan package:discover  || warn "package:discover gagal"
  run_artisan storage:link      || true

  # Build caches
  run_artisan config:cache      || warn "config:cache gagal"
  run_artisan route:cache       || warn "route:cache gagal"
  run_artisan view:cache        || warn "view:cache gagal"

  # Migrasi DB (tidak fail the whole deploy; ubah ke 'set -e' di sini jika mau fail-fast)
  log "Running database migrations (force)"
  if ! run_artisan migrate --force; then
    warn "migrate gagal — cek skema atau migration order."
  fi
else
  warn "artisan tidak ditemukan; melewati langkah Laravel."
fi

# --- activate release (atomic) ---
log "Activating release"
ln -sfn "$RELEASE_DIR" "$CURRENT_LINK"

# --- reload services ---
reload_service() {
  local svc="$1"; [[ -z "$svc" ]] && return 0
  if command -v systemctl >/dev/null 2>&1; then
    if ! maybe_sudo systemctl reload "$svc"; then
      maybe_sudo systemctl restart "$svc" || warn "Gagal reload/restart $svc"
    fi
  else
    if ! maybe_sudo service "$svc" reload; then
      maybe_sudo service "$svc" restart || warn "Gagal reload/restart $svc"
    fi
  fi
}
[[ -n "$FPM_SVC" ]]   && { log "Reloading $FPM_SVC";   reload_service "$FPM_SVC"; }
[[ -n "$NGINX_SVC" ]] && { log "Reloading $NGINX_SVC"; reload_service "$NGINX_SVC"; }

# --- housekeeping: keep N releases ---
log "Cleaning old releases (keep $KEEP)"
if ls -1dt "$RELEASES_DIR"/* >/dev/null 2>&1; then
  ls -1dt "$RELEASES_DIR"/* | tail -n +$((KEEP + 1)) | xargs -r maybe_sudo rm -rf
fi

log "Deployment completed: $RELEASE"
