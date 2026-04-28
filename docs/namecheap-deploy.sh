#!/bin/bash
set -euo pipefail

APP_HOME="${HOME}"
SITE_HOST="${SITE_HOST:-${SUBDOMAIN:-dashboard.subme.pro}}"
REPO_DIR="${REPO_DIR:-${APP_HOME}/subme}"
APP_SLUG="${APP_SLUG:-$(printf '%s' "${SITE_HOST}" | tr '.:/' '---')}"
WEB_DIR="${WEB_DIR:-${APP_HOME}/${SITE_HOST}}"
APP_BASE_DIR="${APP_HOME}/.subme-apps/${APP_SLUG}"
APP_DIR="${APP_BASE_DIR}/dashboard-panel"
SECRETS_DIR="${APP_HOME}/.subme-secrets/${APP_SLUG}"
MYSQL_CONFIG_FILE="${SECRETS_DIR}/mysql.php"
RELEASES_DIR="${APP_HOME}/.subme-releases/${APP_SLUG}"
BACKEND_POINTER_FILE="${WEB_DIR}/.backend-path"
APP_URL="${APP_URL:-https://${SITE_HOST}}"

log() {
  printf '[deploy] %s\n' "$1"
}

fail() {
  printf '[deploy] ERROR: %s\n' "$1" >&2
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

snapshot_current_release() {
  local snapshot_name="$1"
  local snapshot_dir="${RELEASES_DIR}/${snapshot_name}"

  mkdir -p "${snapshot_dir}/web" "${snapshot_dir}/app"

  if [ -d "${WEB_DIR}" ]; then
    sync_dir "${WEB_DIR}" "${snapshot_dir}/web" --exclude 'uploads/'
  fi

  if [ -d "${APP_DIR}" ]; then
    sync_dir "${APP_DIR}" "${snapshot_dir}/app" --exclude 'templates_c/' --exclude 'config/mysql.php'
  fi

  printf '%s\n' "${snapshot_name}" > "${RELEASES_DIR}/latest"
}

if [ ! -d "${REPO_DIR}/.git" ]; then
  fail "Missing git repository at ${REPO_DIR}"
fi

if [ ! -d "${WEB_DIR}" ]; then
  fail "Missing web directory at ${WEB_DIR}"
fi

mkdir -p "${SECRETS_DIR}"
mkdir -p "${RELEASES_DIR}"
mkdir -p "${APP_BASE_DIR}"

if [ ! -f "${MYSQL_CONFIG_FILE}" ]; then
  if [ -f "${APP_DIR}/config/mysql.php" ]; then
    cp "${APP_DIR}/config/mysql.php" "${MYSQL_CONFIG_FILE}"
    log "Saved production mysql.php to ${MYSQL_CONFIG_FILE}"
  else
    fail "Missing ${MYSQL_CONFIG_FILE}. Copy your production mysql.php there first."
  fi
fi

log "Updating git repository"
cd "${REPO_DIR}"
git pull --ff-only origin main

CURRENT_COMMIT="$(git rev-parse --short HEAD)"
SNAPSHOT_NAME="$(date +%Y%m%d-%H%M%S)-${CURRENT_COMMIT}"

if [ -f "${WEB_DIR}/index.php" ] || [ -d "${APP_DIR}" ]; then
  log "Creating rollback snapshot ${SNAPSHOT_NAME}"
  snapshot_current_release "${SNAPSHOT_NAME}"
fi

log "Deploying public files to ${WEB_DIR}"
sync_dir "${REPO_DIR}/public_html" "${WEB_DIR}" --exclude 'uploads/'

log "Deploying backend to ${APP_DIR}"
sync_dir "${REPO_DIR}/dashboard-panel" "${APP_DIR}" --exclude 'templates_c/' --exclude 'config/mysql.php'

mkdir -p "${APP_DIR}/templates_c"
mkdir -p "${WEB_DIR}/uploads"
cp "${MYSQL_CONFIG_FILE}" "${APP_DIR}/config/mysql.php"
printf '%s\n' "${APP_DIR}" > "${BACKEND_POINTER_FILE}"
printf '%s\n' "${WEB_DIR}" > "${APP_DIR}/.public-root-path"

find "${APP_DIR}/templates_c" -mindepth 1 -delete 2>/dev/null || true

chmod -R 775 "${APP_DIR}/templates_c"
chmod -R 775 "${WEB_DIR}/uploads"

log "Deployment finished"
log "Open: ${APP_URL}"
log "Rollback snapshot: ${SNAPSHOT_NAME}"
