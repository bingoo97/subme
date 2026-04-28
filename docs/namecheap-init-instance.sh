#!/bin/bash
set -euo pipefail

SITE_HOST="${1:-${SITE_HOST:-${SUBDOMAIN:-}}}"
APP_HOME="${HOME}"
REPO_DIR="${REPO_DIR:-${APP_HOME}/subme}"

log() {
  printf '[instance-init] %s\n' "$1"
}

fail() {
  printf '[instance-init] ERROR: %s\n' "$1" >&2
  exit 1
}

usage() {
  cat <<'EOF'
Usage:
  ./namecheap-init-instance.sh site.example.com

Example:
  ./namecheap-init-instance.sh dashboard.subme.pro
  SITE_HOST=subme.pro WEB_DIR=~/public_html REPO_DIR=~/app ./namecheap-init-instance.sh
EOF
}

if [ -z "${SITE_HOST}" ]; then
  usage
  fail "Missing site host."
fi

APP_SLUG="${APP_SLUG:-$(printf '%s' "${SITE_HOST}" | tr '.:/' '---')}"
WEB_DIR="${WEB_DIR:-${APP_HOME}/${SITE_HOST}}"
APP_BASE_DIR="${APP_HOME}/.subme-apps/${APP_SLUG}"
APP_DIR="${APP_BASE_DIR}/dashboard-panel"
SECRETS_DIR="${APP_HOME}/.subme-secrets/${APP_SLUG}"
MYSQL_CONFIG_FILE="${SECRETS_DIR}/mysql.php"
RELEASES_DIR="${APP_HOME}/.subme-releases/${APP_SLUG}"
BACKEND_POINTER_FILE="${WEB_DIR}/.backend-path"
SOURCE_CONFIG=""

if [ ! -d "${REPO_DIR}" ]; then
  fail "Missing git repository at ${REPO_DIR}"
fi

mkdir -p "${WEB_DIR}"
mkdir -p "${APP_DIR}"
mkdir -p "${APP_DIR}/templates_c"
mkdir -p "${WEB_DIR}/uploads"
mkdir -p "${SECRETS_DIR}"
mkdir -p "${RELEASES_DIR}"

if [ -f "${APP_HOME}/.subme-secrets/dashboard-subme-pro/mysql.php" ]; then
  SOURCE_CONFIG="${APP_HOME}/.subme-secrets/dashboard-subme-pro/mysql.php"
elif [ -f "${APP_HOME}/dashboard-panel/config/mysql.php" ]; then
  SOURCE_CONFIG="${APP_HOME}/dashboard-panel/config/mysql.php"
elif [ -f "${REPO_DIR}/dashboard-panel/config/mysql.php" ]; then
  SOURCE_CONFIG="${REPO_DIR}/dashboard-panel/config/mysql.php"
fi

if [ ! -f "${MYSQL_CONFIG_FILE}" ]; then
  if [ -n "${SOURCE_CONFIG}" ]; then
    cp "${SOURCE_CONFIG}" "${MYSQL_CONFIG_FILE}"
    log "Created mysql config template at ${MYSQL_CONFIG_FILE}"
  else
    fail "Could not find a source mysql.php template."
  fi
fi

printf '%s\n' "${APP_DIR}" > "${BACKEND_POINTER_FILE}"

chmod -R 775 "${APP_DIR}/templates_c"
chmod -R 775 "${WEB_DIR}/uploads"

log "Instance prepared for ${SITE_HOST}"
log "Web dir: ${WEB_DIR}"
log "Backend dir: ${APP_DIR}"
log "Secrets dir: ${SECRETS_DIR}"
log "Edit DB config: ${MYSQL_CONFIG_FILE}"
log "Deploy with:"
printf 'SITE_HOST=%s APP_SLUG=%s WEB_DIR=%s REPO_DIR=%s %s/docs/namecheap-deploy.sh\n' "${SITE_HOST}" "${APP_SLUG}" "${WEB_DIR}" "${REPO_DIR}" "${REPO_DIR}"
