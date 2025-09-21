#!/usr/bin/env bash
# Deploy script untuk aplikasi Laravel
# Contoh pakai:
#   bash deploy.sh \
#     --base "/var/www/icpos" \
#     --tar "/tmp/release.tar.gz" \
#     --php "/usr/bin/php" \
#     --fpm "php8.3-fpm" \
#     --nginx "nginx" \
#     --release "GITHUB_SHA"

set -Eeuo pipefail
umask 022

log() { printf "\033[1;32m==> %s\033[0m\n" "$*"; }
warn(){ printf "\033[1;33m[warn] %s\033[0m\n" "$*"; }
err() { printf "\033[1;31m[err] %s\033[0m\n" "$*" >&2; }

# --- jalankan pakai sudo bila tersedia & tidak minta password ---
maybe_sudo() {
  if command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    sudo "$@" || true
  else
    "$@" || true
  fi
}

# --- default / arg parsing ---
BASE=""
TAR_FILE=""
PHP_BIN="/usr/bin/php"
FPM_SVC=""
NGINX_SVC=""
RELEASE=""
KEEP="${KEEP:-5}"     # simpan 5 rilis terakhir

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

APP_USER="$(id -un)"
APP_GROUP="$(id -gn)"
RELEASES_DIR="$BASE/releases"
SHARED_DIR="$BASE/shared"
CURRENT_LINK="$BASE/current"
RELEASE_DIR="$RELEASES_DIR/$RELEASE"

log "Deploying release $RELEASE to $BASE"

# --- siapkan struktur direktori ---
log "Prepare directories"
mkdir -p "$RELEASES_DIR" \
         "$SHARED_DIR/storage" \
         "$SHARED_DIR/bootstrap/cache"

# --- ekstrak rilis (tanpa mempertahankan owner dari tar) ---
log "Extracting archive"
mkdir -p "$RELEASE_DIR"
tar --no-same-owner -xzf "$TAR_FILE" -C "$RELEASE_DIR"

# --- shared resources (.env, storage) ---
log "Linking shared resources"
if [[ -f "$SHARED_DIR/.env" ]]; then
  ln -sfn "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
else
  warn "shared .env tidak ditemukan di $SHARED_DIR/.env"
fi

# pastikan folder ada
mkdir -p "$RELEASE_DIR/bootstrap/cache"
# storage di release diganti symlink ke shared
rm -rf "$RELEASE_DIR/storage" || true
ln -sfn "$SHARED_DIR/storage" "$RELEASE_DIR/storage"

# --- permissions: cukup chmod; chown hanya jika sudo tersedia ---
log "Setting permissions"
chmod -R u=rwX,go=rX "$RELEASE_DIR"
chmod -R u=rwX,g=rwX "$SHARED_DIR/storage" "$SHARED_DIR/bootstrap/cache"
# boleh gagal tanpa menghentikan proses jika tidak ada sudo
maybe_sudo chown -R "$APP_USER:$APP_GROUP" "$SHARED_DIR/storage" "$SHARED_DIR/bootstrap/cache"

# --- langkah artisan (aman jika gagal) ---
log "Running artisan optimizations"
pushd "$RELEASE_DIR" >/dev/null

# Jika vendor & autoload sudah dibundel di CI, tidak perlu composer install.
# Jalankan discover + cache agar siap produksi.
if "$PHP_BIN" artisan --version >/dev/null 2>&1; then
  "$PHP_BIN" artisan package:discover --ansi || warn "package:discover gagal (abaikan jika tidak perlu)"
  "$PHP_BIN" artisan config:cache         || warn "config:cache gagal"
  "$PHP_BIN" artisan route:cache          || warn "route:cache gagal"
  "$PHP_BIN" artisan view:cache           || warn "view:cache gagal"
  "$PHP_BIN" artisan storage:link         || true
  # Migrate; jika kamu ingin fail-fast, hapus '|| true'
  log "Running database migrations (force)"
  "$PHP_BIN" artisan migrate --force      || warn "migrate gagal"
else
  warn "artisan tidak ditemukan; lewati langkah optimasi"
fi

popd >/dev/null

# --- aktivasi rilis ---
log "Activating release"
ln -sfn "$RELEASE_DIR" "$CURRENT_LINK"

# --- reload service ---
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

# --- housekeeping: hapus rilis lama ---
log "Cleaning old releases (keep $KEEP)"
# shellcheck disable=SC2012
if ls -1dt "$RELEASES_DIR"/* >/dev/null 2>&1; then
  # tail mulai dari release ke-(KEEP+1)
  ls -1dt "$RELEASES_DIR"/* | tail -n +$((KEEP + 1)) | xargs -r rm -rf
fi

log "Deployment completed: $RELEASE"
