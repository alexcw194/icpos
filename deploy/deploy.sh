#!/usr/bin/env bash
# Deploy script untuk Laravel

set -Eeuo pipefail
umask 022

log()  { printf "\033[1;32m==> %s\033[0m\n" "$*"; }
warn() { printf "\033[1;33m[warn] %s\033[0m\n" "$*"; }
err()  { printf "\033[1;31m[err] %s\033[0m\n" "$*" >&2; }

maybe_sudo() {
  if command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    sudo "$@" || true
  else
    "$@" || true
  fi
}

BASE=""
TAR_FILE=""
PHP_BIN="/usr/bin/php"
FPM_SVC=""
NGINX_SVC=""
RELEASE=""
KEEP="${KEEP:-5}"

usage() {
  cat <<EOF
Usage: $0 --base <dir> --tar <file> [--php <bin>] [--fpm <svc>] [--nginx <svc>] [--release <id>] [--keep N]
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
    -h|--help) usage; exit 0 ;;
    *) err "Unknown arg: $1"; usage; exit 2 ;;
  esac
done

[[ -n "$BASE" && -n "$TAR_FILE" ]] || { usage; exit 2; }
[[ -f "$TAR_FILE" ]] || { err "Tar file not found: $TAR_FILE"; exit 2; }
RELEASE="${RELEASE:-$(date +%Y%m%d%H%M%S)}"

# Web user untuk file yang perlu write (storage + bootstrap/cache)
WEB_USER="www-data"
WEB_GROUP="www-data"

RELEASES_DIR="$BASE/releases"
SHARED_DIR="$BASE/shared"
CURRENT_LINK="$BASE/current"
RELEASE_DIR="$RELEASES_DIR/$RELEASE"

log "Deploying release $RELEASE to $BASE (web user: $WEB_USER:$WEB_GROUP)"

log "Prepare directories"
mkdir -p "$RELEASES_DIR" "$SHARED_DIR/storage" "$SHARED_DIR/bootstrap/cache"

log "Extracting archive"
mkdir -p "$RELEASE_DIR"
# penting: jangan bawa owner dari tarball
tar --no-same-owner -xzf "$TAR_FILE" -C "$RELEASE_DIR"

# Pastikan file artisan ada, kalau tidak -> berhenti (tarball salah)
if [[ ! -f "$RELEASE_DIR/artisan" ]]; then
  err "File 'artisan' tidak ditemukan di $RELEASE_DIR. Tarball kamu tidak berisi root project Laravel."
  err "Perbaiki langkah build tarball di CI supaya memaket seluruh repo (lihat snippet di bawah)."
  exit 127
fi

log "Linking shared resources"
# .env
if [[ -f "$SHARED_DIR/.env" ]]; then
  ln -sfn "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
else
  warn "shared .env tidak ditemukan di $SHARED_DIR/.env"
fi

# storage -> shared
rm -rf "$RELEASE_DIR/storage" || true
ln -sfn "$SHARED_DIR/storage" "$RELEASE_DIR/storage"

# bootstrap/cache -> shared (hindari chmod symlink error)
mkdir -p "$SHARED_DIR/bootstrap/cache"
rm -rf "$RELEASE_DIR/bootstrap/cache" || true
ln -sfn "$SHARED_DIR/bootstrap/cache" "$RELEASE_DIR/bootstrap/cache"

log "Setting permissions (shared only)"
maybe_sudo chown -R "$WEB_USER:$WEB_GROUP" "$SHARED_DIR/storage" "$SHARED_DIR/bootstrap/cache"
chmod -R u=rwX,g=rwX "$SHARED_DIR/storage" "$SHARED_DIR/bootstrap/cache"

log "Running artisan tasks"
pushd "$RELEASE_DIR" >/dev/null
if "$PHP_BIN" -v >/dev/null 2>&1; then
  # optional: generate APP_KEY jika kosong
  if [[ -f "$RELEASE_DIR/.env" ]] && grep -qE '^APP_KEY="?$' "$RELEASE_DIR/.env"; then
    "$PHP_BIN" artisan key:generate --force || warn "key:generate gagal (cek permission .env)"
  fi
  "$PHP_BIN" artisan package:discover --ansi || warn "package:discover gagal"
  "$PHP_BIN" artisan config:cache         || warn "config:cache gagal"
  "$PHP_BIN" artisan route:cache          || warn "route:cache gagal"
  "$PHP_BIN" artisan view:cache           || warn "view:cache gagal"
  "$PHP_BIN" artisan storage:link         || true
  log "Running database migrations (force)"
  "$PHP_BIN" artisan migrate --force      || warn "migrate gagal"
else
  warn "PHP tidak bisa dijalankan dari $PHP_BIN"
fi
popd >/dev/null

log "Activating release"
ln -sfn "$RELEASE_DIR" "$CURRENT_LINK"

reload_service() {
  local svc="$1"
  [[ -z "$svc" ]] && return 0
  if command -v systemctl >/dev/null 2>&1; then
    maybe_sudo systemctl reload "$svc" || maybe_sudo systemctl restart "$svc"
  else
    maybe_sudo service "$svc" reload || maybe_sudo service "$svc" restart
  fi
}

[[ -n "$FPM_SVC" ]]   && { log "Reloading $FPM_SVC";   reload_service "$FPM_SVC"; }
[[ -n "$NGINX_SVC" ]] && { log "Reloading $NGINX_SVC"; reload_service "$NGINX_SVC"; }

log "Cleaning old releases (keep $KEEP)"
# jangan pakai xargs ke fungsi shell; gunakan loop
mapfile -t OLD < <(ls -1dt "$RELEASES_DIR"/* 2>/dev/null | tail -n +$((KEEP + 1)) || true)
if ((${#OLD[@]})); then
  for d in "${OLD[@]}"; do
    rm -rf "$d" || maybe_sudo rm -rf "$d"
  done
fi

log "Deployment completed: $RELEASE"
