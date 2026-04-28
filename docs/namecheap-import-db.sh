#!/bin/bash
set -euo pipefail

SQL_FILE="${1:-}"
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"

log() {
  printf '[db-import] %s\n' "$1"
}

fail() {
  printf '[db-import] ERROR: %s\n' "$1" >&2
  exit 1
}

usage() {
  cat <<'EOF'
Usage:
  DB_NAME=your_db DB_USER=your_user ./namecheap-import-db.sh /path/to/file.sql

Optional:
  DB_HOST=localhost

Example:
  DB_NAME=submtosl_dashboard DB_USER=submtosl_submepro ./namecheap-import-db.sh ~/RESELLER/reseller_v2_namecheap.sql

Important:
  This script restores an arbitrary SQL snapshot file.
  It is NOT the canonical source-of-truth bootstrap for the project schema.
  For rebuilding the database from the repository SQL, use:
    ./namecheap-import-canonical-db.sh
EOF
}

if [ -z "${SQL_FILE}" ]; then
  usage
  fail "Missing SQL file path."
fi

if [ ! -f "${SQL_FILE}" ]; then
  fail "SQL file not found: ${SQL_FILE}"
fi

if [ -z "${DB_NAME}" ] || [ -z "${DB_USER}" ]; then
  usage
  fail "Set DB_NAME and DB_USER before running the import."
fi

log "Importing snapshot ${SQL_FILE} into ${DB_NAME} on ${DB_HOST}"
mysql -h "${DB_HOST}" -u "${DB_USER}" -p "${DB_NAME}" < "${SQL_FILE}"
log "Snapshot import finished"
