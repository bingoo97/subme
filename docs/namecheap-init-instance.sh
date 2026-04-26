#!/bin/bash
set -euo pipefail

SUBDOMAIN="${1:-${SUBDOMAIN:-}}"
APP_HOME="${HOME}"
REPO_DIR="${APP_HOME}/subme"

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
  ./namecheap-init-instance.sh subdomain.example.com

Example:
  ./namecheap-init-instance.sh dashboard.subme.pro
  ./namecheap-init-instance.sh panel.subme.pro
EOF
}

if [ -z "${SUBDOMAIN}" ]; then
  usage
  fail "Missing subdomain."
fi

APP_SLUG="$(printf '%s' "${SUBDOMAIN}" | tr '.:/' '---')"
WEB_DIR="${APP_HOME}/${SUBDOMAIN}"
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

log "Instance prepared for ${SUBDOMAIN}"
log "Web dir: ${WEB_DIR}"
log "Backend dir: ${APP_DIR}"
log "Secrets dir: ${SECRETS_DIR}"
log "Edit DB config: ${MYSQL_CONFIG_FILE}"
log "Deploy with:"
printf 'SUBDOMAIN=%s APP_SLUG=%s %s/docs/namecheap-deploy.sh\n' "${SUBDOMAIN}" "${APP_SLUG}" "${REPO_DIR}"
