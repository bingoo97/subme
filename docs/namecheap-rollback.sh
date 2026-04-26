#!/bin/bash
set -euo pipefail

APP_HOME="${HOME}"
SUBDOMAIN="${SUBDOMAIN:-dashboard.subme.pro}"
APP_SLUG="${APP_SLUG:-$(printf '%s' "${SUBDOMAIN}" | tr '.:/' '---')}"
WEB_DIR="${APP_HOME}/${SUBDOMAIN}"
APP_DIR="${APP_HOME}/.subme-apps/${APP_SLUG}/dashboard-panel"
SECRETS_DIR="${APP_HOME}/.subme-secrets/${APP_SLUG}"
MYSQL_CONFIG_FILE="${SECRETS_DIR}/mysql.php"
RELEASES_DIR="${APP_HOME}/.subme-releases/${APP_SLUG}"
BACKEND_POINTER_FILE="${WEB_DIR}/.backend-path"

log() {
  printf '[rollback] %s\n' "$1"
}

fail() {
  printf '[rollback] ERROR: %s\n' "$1" >&2
  exit 1
}

sync_dir() {
  local src="$1"
  local dst="$2"
  shift 2

  mkdir -p "${dst}"

  if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete "$@" "${src}/" "${dst}/"
  else
    log "rsync not found, using cp fallback for ${dst}"
    cp -R "${src}/." "${dst}/"
  fi
}

if [ ! -d "${RELEASES_DIR}" ]; then
  fail "Missing ${RELEASES_DIR}. No deploy snapshots found."
fi

if [ ! -f "${RELEASES_DIR}/latest" ]; then
  fail "Missing ${RELEASES_DIR}/latest. No rollback target found."
fi

SNAPSHOT_NAME="${1:-$(cat "${RELEASES_DIR}/latest")}"
SNAPSHOT_DIR="${RELEASES_DIR}/${SNAPSHOT_NAME}"

if [ ! -d "${SNAPSHOT_DIR}/web" ] || [ ! -d "${SNAPSHOT_DIR}/app" ]; then
  fail "Incomplete snapshot: ${SNAPSHOT_NAME}"
fi

if [ ! -f "${MYSQL_CONFIG_FILE}" ]; then
  fail "Missing production mysql.php backup at ${MYSQL_CONFIG_FILE}"
fi

log "Restoring snapshot ${SNAPSHOT_NAME}"
sync_dir "${SNAPSHOT_DIR}/web" "${WEB_DIR}" --exclude 'uploads/'
sync_dir "${SNAPSHOT_DIR}/app" "${APP_DIR}" --exclude 'templates_c/' --exclude 'config/mysql.php'

mkdir -p "${APP_DIR}/templates_c"
mkdir -p "${WEB_DIR}/uploads"
cp "${MYSQL_CONFIG_FILE}" "${APP_DIR}/config/mysql.php"
printf '%s\n' "${APP_DIR}" > "${BACKEND_POINTER_FILE}"

chmod -R 775 "${APP_DIR}/templates_c"
chmod -R 775 "${WEB_DIR}/uploads"

log "Rollback finished"
log "Restored snapshot: ${SNAPSHOT_NAME}"
