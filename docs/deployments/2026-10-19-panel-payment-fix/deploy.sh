#!/usr/bin/env bash
set -euo pipefail
umask 077
SITE="$1"
case "$SITE:$USER" in demo:submtosl|dashboard:submtosl) ;; *) exit 1;; esac
STAGE="$HOME/.subme-deploy-stage/panel-payment-fix-20261019-01"
WEB="$HOME/$SITE.subme.pro"
APP="$HOME/.subme-apps/$SITE-subme-pro/dashboard-panel"
BACKUP="$HOME/.subme-deploy-backups/panel-payment-fix-20261019-01/$SITE"
test "$(tr -d '\r\n' < "$WEB/.backend-path")" = "$APP"
test "$(tr -d '\r\n' < "$APP/.public-root-path")" = "$WEB"
test ! -e "$BACKUP/COMPLETE"
mapfile -t FILES < "$STAGE/deploy-tools/files.txt"
test "${#FILES[@]}" = 5
(cd "$STAGE" && sha256sum -c deploy-tools/manifest.sha256 >/dev/null)
mkdir -p "$BACKUP"
sha256sum "$APP/config/mysql.php" "$WEB/.backend-path" "$APP/.public-root-path" "$WEB/.htaccess" > "$BACKUP/protected-before.sha256"
crontab -l > "$BACKUP/crontab-before.txt"
for file in "${FILES[@]}"; do
 case "$file" in dashboard-panel/bootstrap/panel_subscription.php|dashboard-panel/admin/panel_subscription.php|dashboard-panel/admin/views/panel_tiers_modal.php|public_html/assets/js/panel-subscription.js|public_html/assets/css/admin.css) ;; *) exit 1;; esac
 if [[ "$file" == *.php ]]; then php -l "$STAGE/$file" >/dev/null; fi
 if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
 test -f "$target"; test ! -L "$target"
 expected=$(awk -v f="$file" '$2==f {print $1}' "$STAGE/deploy-tools/baseline.sha256")
 test "$(sha256sum "$target" | cut -d ' ' -f1)" = "$expected"
 mkdir -p "$BACKUP/files/$(dirname "$file")"; cp -p "$target" "$BACKUP/files/$file"
done
php "$STAGE/deploy-tools/state.php" snapshot "$SITE" "$STAGE"
gzip -t "$BACKUP/database.sql.gz"
install_started=0
rollback(){
 rc=$?;trap - ERR
 if [[ "$install_started" == 1 ]]; then
  for file in "${FILES[@]}"; do
   if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
   cp -p "$BACKUP/files/$file" "$target"
  done
  echo "Previous runtime restored; database not changed." >&2
 fi
 exit "$rc"
}
trap rollback ERR
install_started=1
for file in "${FILES[@]}"; do
 if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
 cp "$STAGE/$file" "$target.panel-payment-fix.tmp";chmod 644 "$target.panel-payment-fix.tmp";mv -f "$target.panel-payment-fix.tmp" "$target"
done
for file in "${FILES[@]}"; do
 if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
 test "$(sha256sum "$STAGE/$file" | cut -d ' ' -f1)" = "$(sha256sum "$target" | cut -d ' ' -f1)"
done
sha256sum -c "$BACKUP/protected-before.sha256"
crontab -l > "$BACKUP/crontab-after.txt";cmp "$BACKUP/crontab-before.txt" "$BACKUP/crontab-after.txt"
php "$STAGE/deploy-tools/state.php" verify "$SITE" "$STAGE"
php "$STAGE/deploy-tools/probe.php" "$APP"
php "$STAGE/deploy-tools/http-check.php" "$SITE"
touch "$BACKUP/COMPLETE"
printf 'DEPLOY_OK site=%s files=5 settings_and_data_preserved\n' "$SITE"
