#!/usr/bin/env bash
set -euo pipefail
umask 077
REVISION="$1"
SITE="$2"
case "$SITE:$USER" in demo:submtosl|dashboard:submtosl) ;; *) echo 'Unsupported deployment target/account' >&2; exit 1;; esac
[[ "$REVISION" =~ ^[a-f0-9]{40}$ ]]
REPO="$HOME/subme"
WEB="$HOME/$SITE.subme.pro"
APP="$HOME/.subme-apps/$SITE-subme-pro/dashboard-panel"
TOOLS="docs/deployments/2026-10-18-provider-off"
RELEASE="provider-off-20261018-${REVISION:0:12}"
STAGE="$HOME/.subme-deploy-stage/$RELEASE-$SITE"
BACKUP="$HOME/.subme-deploy-backups/$RELEASE/$SITE"
test "$(tr -d '\r\n' < "$WEB/.backend-path")" = "$APP"
test "$(tr -d '\r\n' < "$APP/.public-root-path")" = "$WEB"
test ! -e "$BACKUP/COMPLETE"
mkdir -p "$STAGE" "$BACKUP"
git -C "$REPO" show "$REVISION:$TOOLS/files.txt" > "$STAGE/files.txt"
while IFS= read -r file; do
    case "$file" in dashboard-panel/admin/provider_remote.php|dashboard-panel/admin/provider_browser.php|dashboard-panel/admin/provider_remote_endpoint.php|dashboard-panel/admin/provider_browser_endpoint.php|dashboard-panel/admin/bootstrap.php|dashboard-panel/admin/index.php|public_html/assets/css/admin.css|public_html/assets/js/admin.js|public_html/assets/js/provider-browser.js|public_html/admin/provider_remote.php|public_html/admin/provider_browser.php|dashboard-panel/migrations/040_provider_remote_login.sql) ;;
    *) echo "Unexpected manifest entry: $file" >&2; exit 1;; esac
done < "$STAGE/files.txt"
mapfile -t FILES < "$STAGE/files.txt"
git -C "$REPO" archive "$REVISION" "${FILES[@]}" "$TOOLS" | tar -xf - -C "$STAGE"
sha256sum "$APP/config/mysql.php" "$WEB/.backend-path" "$APP/.public-root-path" "$WEB/.htaccess" > "$BACKUP/protected-before.sha256"
printf '%s\n' "$REVISION" > "$BACKUP/revision"
crontab -l > "$BACKUP/crontab-before.txt"
while IFS= read -r file; do
    test -f "$STAGE/$file"
    if [[ "$file" == *.php ]]; then php -l "$STAGE/$file" >/dev/null; fi
    if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
    test ! -L "$target"
    expected="$(awk -v f="$file" '$2==f {print $1}' "$STAGE/$TOOLS/baseline.sha256")"
    test -n "$expected"
    if test -f "$target"; then
        current="$(sha256sum "$target" | cut -d ' ' -f1)"
        staged="$(sha256sum "$STAGE/$file" | cut -d ' ' -f1)"
        if [[ "$current" != "$expected" && "$current" != "$staged" ]]; then echo "Unexpected live modification: $file" >&2; exit 1; fi
        mkdir -p "$BACKUP/files/$(dirname "$file")"; cp -p "$target" "$BACKUP/files/$file"
    else
        test "$expected" = NEW
        printf '%s\n' "$file" >> "$BACKUP/created-files.txt"
    fi
done < "$STAGE/files.txt"
php "$STAGE/$TOOLS/state.php" snapshot "$APP" "$BACKUP"
php "$STAGE/$TOOLS/state.php" migrate "$APP" "$BACKUP" "$STAGE"
install_started=0
rollback_code() {
    rc=$?
    trap - ERR
    if [[ "$install_started" == 1 ]]; then
        while IFS= read -r file; do
            if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
            if test -f "$BACKUP/files/$file"; then cp -p "$BACKUP/files/$file" "$target"; else php -r 'if(is_file($argv[1]))unlink($argv[1]);' "$target"; fi
        done < "$STAGE/files.txt"
        echo "Code restored from target backup; provider switches remain OFF. Backup: $BACKUP" >&2
    fi
    exit "$rc"
}
trap rollback_code ERR
install_started=1
# Helpers precede their callers; public entry points are installed last.
for file in "${FILES[@]}"; do
    case "$file" in dashboard-panel/admin/bootstrap.php|dashboard-panel/admin/index.php|public_html/*) continue;; esac
    target="$APP/${file#dashboard-panel/}"
    mkdir -p "$(dirname "$target")"; cp "$STAGE/$file" "$target.$RELEASE.tmp"; chmod 644 "$target.$RELEASE.tmp"; mv -f "$target.$RELEASE.tmp" "$target"
done
for file in dashboard-panel/admin/bootstrap.php dashboard-panel/admin/index.php; do
    target="$APP/${file#dashboard-panel/}"
    cp "$STAGE/$file" "$target.$RELEASE.tmp"; chmod 644 "$target.$RELEASE.tmp"; mv -f "$target.$RELEASE.tmp" "$target"
done
for file in "${FILES[@]}"; do
    [[ "$file" == public_html/* ]] || continue
    target="$WEB/${file#public_html/}"
    mkdir -p "$(dirname "$target")"; cp "$STAGE/$file" "$target.$RELEASE.tmp"; chmod 644 "$target.$RELEASE.tmp"; mv -f "$target.$RELEASE.tmp" "$target"
done
for file in "${FILES[@]}"; do
    if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
    test "$(sha256sum "$STAGE/$file" | cut -d ' ' -f1)" = "$(sha256sum "$target" | cut -d ' ' -f1)"
done
sha256sum -c "$BACKUP/protected-before.sha256"
crontab -l > "$BACKUP/crontab-after.txt"; cmp "$BACKUP/crontab-before.txt" "$BACKUP/crontab-after.txt"
php "$STAGE/$TOOLS/state.php" verify "$APP" "$BACKUP"
touch "$BACKUP/COMPLETE"
printf 'DEPLOY_OK site=%s revision=%s provider_login=OFF subscriptions=OFF backup=%s\n' "$SITE" "$REVISION" "$BACKUP"
