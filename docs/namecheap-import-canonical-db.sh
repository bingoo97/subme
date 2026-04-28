#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
CONFIRM_RESET="${CONFIRM_RESET:-}"

CORE_SCHEMA="${REPO_ROOT}/dashboard-panel/database/v2/001_core_schema.sql"
SEED_DEFAULTS="${REPO_ROOT}/dashboard-panel/database/v2/002_seed_defaults.sql"
OPERATIONAL_VIEWS="${REPO_ROOT}/dashboard-panel/database/v2/003_operational_views.sql"

log() {
  printf '[db-canonical-import] %s\n' "$1"
}

fail() {
  printf '[db-canonical-import] ERROR: %s\n' "$1" >&2
  exit 1
}

usage() {
  cat <<'EOF'
Usage:
  DB_NAME=your_db DB_USER=your_user CONFIRM_RESET=YES ./namecheap-import-canonical-db.sh

Optional:
  DB_HOST=localhost

Important:
  This script imports the canonical schema from the repository:
  - 001_core_schema.sql
  - 002_seed_defaults.sql
  - 003_operational_views.sql

  Treat it as the clean bootstrap / rebuild path.
  It is not meant to preserve existing data.
EOF
}

if [ -z "${DB_NAME}" ] || [ -z "${DB_USER}" ]; then
  usage
  fail "Set DB_NAME and DB_USER before running the import."
fi

if [ "${CONFIRM_RESET}" != "YES" ]; then
  usage
  fail "Set CONFIRM_RESET=YES to confirm that you want to rebuild the database from canonical repo SQL."
fi

for required_file in "${CORE_SCHEMA}" "${SEED_DEFAULTS}" "${OPERATIONAL_VIEWS}"; do
  if [ ! -f "${required_file}" ]; then
    fail "Missing required SQL file: ${required_file}"
  fi
done

tmp_sql="$(mktemp)"
trap 'rm -f "${tmp_sql}"' EXIT

cat "${CORE_SCHEMA}" "${SEED_DEFAULTS}" "${OPERATIONAL_VIEWS}" > "${tmp_sql}"

log "Importing canonical repository SQL into ${DB_NAME} on ${DB_HOST}"
mysql -h "${DB_HOST}" -u "${DB_USER}" -p "${DB_NAME}" < "${tmp_sql}"
log "Canonical import finished"
