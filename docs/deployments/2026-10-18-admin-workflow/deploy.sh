#!/usr/bin/env bash
set -euo pipefail
umask 077
REVISION="$1"
SITE="$2"
case "$SITE:$USER" in demo:submtosl|dashboard:submtosl) ;; *) exit 1;; esac
[[ "$REVISION" =~ ^[a-f0-9]{40}$ ]]
REPO="$HOME/subme"
WEB="$HOME/$SITE.subme.pro"
APP="$HOME/.subme-apps/$SITE-subme-pro/dashboard-panel"
TOOLS=docs/deployments/2026-10-18-admin-workflow
RELEASE="workflow-20261018-${REVISION:0:12}"
STAGE="$HOME/.subme-deploy-stage/$RELEASE-$SITE"
BACKUP="$HOME/.subme-deploy-backups/$RELEASE/$SITE"
test "$(tr -d '\r\n' < "$WEB/.backend-path")" = "$APP"
test "$(tr -d '\r\n' < "$APP/.public-root-path")" = "$WEB"
test ! -e "$BACKUP/COMPLETE"
mkdir -p "$STAGE" "$BACKUP"
git -C "$REPO" show "$REVISION:$TOOLS/files.txt" > "$STAGE/files.txt"
mapfile -t FILES < "$STAGE/files.txt"
test "${#FILES[@]}" = 32
for file in "${FILES[@]}"; do
    case "$file" in dashboard-panel/admin/bootstrap.php|dashboard-panel/admin/dashboard_sales.php|dashboard-panel/admin/index.php|dashboard-panel/admin/locales/en.php|dashboard-panel/admin/locales/pl.php|dashboard-panel/admin/partials/notification-order-tasks.php|dashboard-panel/admin/topbar_state.php|dashboard-panel/admin/wallets.php|dashboard-panel/bootstrap/application.php|dashboard-panel/bootstrap/chat.php|dashboard-panel/bootstrap/chat_groups.php|dashboard-panel/bootstrap/read_maintenance.php|dashboard-panel/check_chat.php|dashboard-panel/locales/en.php|dashboard-panel/locales/pl.php|dashboard-panel/migrations/041_panel_subscription_notifications.sql|dashboard-panel/pages/profil/orders.php|dashboard-panel/pages/profil/orders_add.php|dashboard-panel/pages/profil/orders_check.php|dashboard-panel/pages/profil/orders_payment.php|dashboard-panel/scripts/check_data.php|dashboard-panel/templates/messanger.tpl|dashboard-panel/templates/profil/orders.tpl|dashboard-panel/templates/profil/orders_payment.tpl|dashboard-panel/templates/profil/orders_reseller.tpl|public_html/assets/css/admin-dashboard.css|public_html/assets/css/admin.css|public_html/assets/css/messanger.css|public_html/assets/css/style.css|public_html/assets/js/admin-dashboard.js|public_html/assets/js/admin.js|public_html/assets/js/messanger.js) ;;
    *) echo 'Unexpected manifest path' >&2; exit 1;; esac
done
git -C "$REPO" archive "$REVISION" "${FILES[@]}" "$TOOLS" | tar -xf - -C "$STAGE"
sha256sum "$APP/config/mysql.php" "$WEB/.backend-path" "$APP/.public-root-path" "$WEB/.htaccess" > "$BACKUP/protected-before.sha256"
crontab -l > "$BACKUP/crontab-before.txt"
cp "$STAGE/files.txt" "$BACKUP/files.txt"
printf '%s\n' "$REVISION" > "$BACKUP/revision"
date -u +%Y-%m-%dT%H:%M:%SZ > "$BACKUP/started-at"
for file in "${FILES[@]}"; do
    test -f "$STAGE/$file"
    if [[ "$file" == *.php ]]; then php -l "$STAGE/$file" >/dev/null; fi
    if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
    test ! -L "$target"
    expected="$(awk -v f="$file" '$2==f {print $1}' "$STAGE/$TOOLS/baseline-$SITE.sha256")"
    test -n "$expected"
    if test -f "$target"; then
        current="$(sha256sum "$target" | cut -d ' ' -f1)"
        staged="$(sha256sum "$STAGE/$file" | cut -d ' ' -f1)"
        if [[ "$current" != "$expected" && "$current" != "$staged" ]]; then echo "Live file changed after preflight: $file" >&2; exit 1; fi
        mkdir -p "$BACKUP/files/$(dirname "$file")"; cp -p "$target" "$BACKUP/files/$file"
    else
        test "$expected" = NEW
        printf '%s\n' "$file" >> "$BACKUP/created-files.txt"
    fi
done
php "$STAGE/$TOOLS/state.php" snapshot "$APP" "$BACKUP"
php "$STAGE/$TOOLS/state.php" migrate "$APP" "$BACKUP" "$STAGE"
php "$STAGE/$TOOLS/state.php" verify "$APP" "$BACKUP"
install_started=0
rollback_code() {
    rc=$?
    trap - ERR
    if [[ "$install_started" == 1 ]]; then
        for file in "${FILES[@]}"; do
            if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
            if test -f "$BACKUP/files/$file"; then cp -p "$BACKUP/files/$file" "$target"; else php -r 'if(is_file($argv[1]))unlink($argv[1]);' "$target"; fi
        done
        echo "Target code restored. Provider stays OFF. Backup: $BACKUP" >&2
    fi
    exit "$rc"
}
trap rollback_code ERR
install_file() {
    local file="$1" target
    if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
    mkdir -p "$(dirname "$target")"
    cp "$STAGE/$file" "$target.$RELEASE.tmp"; chmod 644 "$target.$RELEASE.tmp"; mv -f "$target.$RELEASE.tmp" "$target"
}
install_started=1
install_file dashboard-panel/bootstrap/read_maintenance.php
install_file dashboard-panel/bootstrap/application.php
for file in "${FILES[@]}"; do
    case "$file" in dashboard-panel/bootstrap/read_maintenance.php|dashboard-panel/bootstrap/application.php|dashboard-panel/admin/bootstrap.php|dashboard-panel/admin/index.php|public_html/*) continue;; esac
    install_file "$file"
done
install_file dashboard-panel/admin/bootstrap.php
install_file dashboard-panel/admin/index.php
for file in "${FILES[@]}"; do [[ "$file" != public_html/* ]] || install_file "$file"; done
for file in "${FILES[@]}"; do
    if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
    test "$(sha256sum "$STAGE/$file" | cut -d ' ' -f1)" = "$(sha256sum "$target" | cut -d ' ' -f1)"
done
sha256sum -c "$BACKUP/protected-before.sha256"
crontab -l > "$BACKUP/crontab-after.txt"; cmp "$BACKUP/crontab-before.txt" "$BACKUP/crontab-after.txt"
php "$STAGE/$TOOLS/state.php" verify "$APP" "$BACKUP"
touch "$BACKUP/COMPLETE"
printf 'DEPLOY_OK site=%s files=32 revision=%s provider_login=OFF subscriptions=OFF backup=%s\n' "$SITE" "$REVISION" "$BACKUP"
