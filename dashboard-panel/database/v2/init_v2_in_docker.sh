#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

DB_CONTAINER="${DB_CONTAINER:-reseller_db_local}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-reseller_root_local}"
APP_DB_USER="${APP_DB_USER:-marbodz_reseller}"
APP_DB_PASSWORD="${APP_DB_PASSWORD:-Reseller12@}"
LEGACY_DB="${LEGACY_DB:-marbodz_reseller}"
NEW_DB="${NEW_DB:-reseller_v2}"

MYSQL_ROOT=(docker exec -i "${DB_CONTAINER}" mysql --default-character-set=utf8mb4 -uroot "-p${DB_ROOT_PASSWORD}")

echo "Creating database ${NEW_DB} in container ${DB_CONTAINER}..."
"${MYSQL_ROOT[@]}" -e "CREATE DATABASE IF NOT EXISTS \`${NEW_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"${MYSQL_ROOT[@]}" -e "GRANT ALL PRIVILEGES ON \`${NEW_DB}\`.* TO '${APP_DB_USER}'@'%'; FLUSH PRIVILEGES;"

echo "Applying core schema..."
"${MYSQL_ROOT[@]}" "${NEW_DB}" < "${SCRIPT_DIR}/001_core_schema.sql"

echo "Applying seed defaults..."
"${MYSQL_ROOT[@]}" "${NEW_DB}" < "${SCRIPT_DIR}/002_seed_defaults.sql"

echo "Applying operational views..."
"${MYSQL_ROOT[@]}" "${NEW_DB}" < "${SCRIPT_DIR}/003_operational_views.sql"

echo "Importing legacy data from ${LEGACY_DB}..."
tmp_sql="$(mktemp)"
cleanup() {
  rm -f "${tmp_sql}"
}
trap cleanup EXIT

sed \
  -e "s/__LEGACY_DB__/${LEGACY_DB}/g" \
  -e "s/__NEW_DB__/${NEW_DB}/g" \
  "${SCRIPT_DIR}/004_import_from_legacy.sql" > "${tmp_sql}"

"${MYSQL_ROOT[@]}" < "${tmp_sql}"

echo "Repairing Polish UTF-8 static pages..."
"${MYSQL_ROOT[@]}" "${NEW_DB}" < "${SCRIPT_DIR}/005_fix_polish_static_pages_utf8.sql"

echo "Import summary for ${NEW_DB}:"
"${MYSQL_ROOT[@]}" -e "
SELECT 'customers' AS table_name, COUNT(*) AS row_count FROM \`${NEW_DB}\`.\`customers\`
UNION ALL SELECT 'admin_users', COUNT(*) FROM \`${NEW_DB}\`.\`admin_users\`
UNION ALL SELECT 'products', COUNT(*) FROM \`${NEW_DB}\`.\`products\`
UNION ALL SELECT 'orders', COUNT(*) FROM \`${NEW_DB}\`.\`orders\`
UNION ALL SELECT 'crypto_wallet_addresses', COUNT(*) FROM \`${NEW_DB}\`.\`crypto_wallet_addresses\`
UNION ALL SELECT 'crypto_deposit_requests', COUNT(*) FROM \`${NEW_DB}\`.\`crypto_deposit_requests\`
UNION ALL SELECT 'crypto_deposit_transactions', COUNT(*) FROM \`${NEW_DB}\`.\`crypto_deposit_transactions\`
UNION ALL SELECT 'support_conversations', COUNT(*) FROM \`${NEW_DB}\`.\`support_conversations\`
UNION ALL SELECT 'support_messages', COUNT(*) FROM \`${NEW_DB}\`.\`support_messages\`
UNION ALL SELECT 'news_posts', COUNT(*) FROM \`${NEW_DB}\`.\`news_posts\`
UNION ALL SELECT 'static_pages', COUNT(*) FROM \`${NEW_DB}\`.\`static_pages\`
UNION ALL SELECT 'email_templates', COUNT(*) FROM \`${NEW_DB}\`.\`email_templates\`;
"

echo
echo "The new database ${NEW_DB} is ready."
echo "Current app still points to legacy schema until the application code is switched."
