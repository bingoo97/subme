#!/usr/bin/env bash
set -euo pipefail
umask 077
REVISION="$1"
SITE="$2"
case "$SITE" in demo|dashboard) ;; *) echo 'Unsupported deployment target' >&2; exit 1;; esac
[[ "$REVISION" =~ ^[a-f0-9]{40}$ ]]
REPO="$HOME/subme"
WEB="$HOME/$SITE.subme.pro"
APP="$HOME/.subme-apps/$SITE-subme-pro/dashboard-panel"
RELEASE="admin-20261018-${REVISION:0:12}"
STAGE="$HOME/.subme-deploy-stage/$RELEASE-$SITE"
BACKUP="$HOME/.subme-deploy-backups/$RELEASE/$SITE"
TOOLS="docs/deployments/2026-10-18-subdomains"
test "$(cat "$WEB/.backend-path")" = "$APP"
test -d "$APP"
test ! -e "$BACKUP/COMPLETE"
mkdir -p "$STAGE" "$BACKUP"
git -C "$REPO" show "$REVISION:$TOOLS/files.txt" > "$STAGE/files.txt"
while IFS= read -r file; do
    case "$file" in dashboard-panel/admin/*.php|dashboard-panel/bootstrap/*.php|dashboard-panel/check_chat.php|dashboard-panel/check_payment.php|dashboard-panel/config/check_crypto.php|dashboard-panel/pages/profil/*.php|dashboard-panel/templates/profil/payments_crypto.tpl|dashboard-panel/database/migrations/019_preserve_original_sales.sql|dashboard-panel/database/migrations/020_dashboard_sales_query_index.sql|dashboard-panel/database/migrations/021_crypto_scan_queue.sql|public_html/admin/crypto_status.php|public_html/admin/topbar_state.php|public_html/assets/css/admin.css|public_html/assets/css/admin-dashboard.css|public_html/assets/js/admin.js|public_html/assets/js/admin-dashboard.js) ;;
    *) echo "Unexpected manifest entry: $file" >&2; exit 1;; esac
    case "$file" in *..*|*mysql.php*|*uploads*|*storage*|*sessions*|*.env*) exit 1;; esac
done < "$STAGE/files.txt"
mapfile -t FILES < "$STAGE/files.txt"
git -C "$REPO" archive "$REVISION" "${FILES[@]}" "$TOOLS" | tar -xf - -C "$STAGE"
while IFS= read -r file; do
    test -f "$STAGE/$file"
    if [[ "$file" == *.php ]]; then php -l "$STAGE/$file" > /dev/null; fi
done < "$STAGE/files.txt"
sha256sum "$APP/config/mysql.php" "$WEB/.backend-path" "$APP/.public-root-path" > "$BACKUP/protected-before.sha256"
printf '%s\n' "$REVISION" > "$BACKUP/revision"
while IFS= read -r file; do
    if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
    if test -f "$target"; then mkdir -p "$BACKUP/files/$(dirname "$file")"; cp -p "$target" "$BACKUP/files/$file"; else printf '%s\n' "$file" >> "$BACKUP/created-files.txt"; fi
done < "$STAGE/files.txt"
php "$STAGE/$TOOLS/preserve-admin-sessions.php" "$APP"
php "$STAGE/$TOOLS/migrate.php" "$APP" "$STAGE" "$BACKUP"
# New helpers are installed before their callers. No directory synchronization or deletion.
mapfile -t ORDERED < <(printf '%s\n' "${FILES[@]}" | sort -k1,1)
for file in "${ORDERED[@]}"; do
    case "$file" in dashboard-panel/admin/index.php|dashboard-panel/admin/bootstrap.php|dashboard-panel/bootstrap/application.php|public_html/*) continue;; esac
    target="$APP/${file#dashboard-panel/}"
    mkdir -p "$(dirname "$target")"; cp "$STAGE/$file" "$target.$RELEASE.tmp"; chmod 644 "$target.$RELEASE.tmp"; mv -f "$target.$RELEASE.tmp" "$target"
done
for file in dashboard-panel/bootstrap/application.php dashboard-panel/admin/bootstrap.php dashboard-panel/admin/index.php; do
    target="$APP/${file#dashboard-panel/}"
    cp "$STAGE/$file" "$target.$RELEASE.tmp"; chmod 644 "$target.$RELEASE.tmp"; mv -f "$target.$RELEASE.tmp" "$target"
done
while IFS= read -r file; do
    [[ "$file" == public_html/* ]] || continue
    target="$WEB/${file#public_html/}"
    mkdir -p "$(dirname "$target")"; cp "$STAGE/$file" "$target.$RELEASE.tmp"; chmod 644 "$target.$RELEASE.tmp"; mv -f "$target.$RELEASE.tmp" "$target"
done < "$STAGE/files.txt"
while IFS= read -r file; do
    if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
    test "$(sha256sum "$STAGE/$file" | cut -d ' ' -f1)" = "$(sha256sum "$target" | cut -d ' ' -f1)"
done < "$STAGE/files.txt"
sha256sum -c "$BACKUP/protected-before.sha256"
# Only compiled templates are disposable. Customer uploads and live sessions are untouched.
if test -d "$APP/templates_c"; then find "$APP/templates_c" -type f -name '*.php' -delete; fi
touch "$BACKUP/COMPLETE"
printf 'DEPLOY_OK site=%s revision=%s backup=%s\n' "$SITE" "$REVISION" "$BACKUP"
