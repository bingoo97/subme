#!/usr/bin/env bash
set -euo pipefail
umask 077
SITE="$1"
case "$SITE:$USER" in demo:submtosl|dashboard:submtosl) ;; *) exit 1;; esac
STAGE="$HOME/.subme-deploy-stage/customer-import-20261020-b4a346b"
WEB="$HOME/$SITE.subme.pro"
APP="$HOME/.subme-apps/$SITE-subme-pro/dashboard-panel"
BACKUP="$HOME/.subme-deploy-backups/customer-import-20261020-b4a346b/$SITE"
test "$(tr -d '\r\n' < "$WEB/.backend-path")" = "$APP"
test "$(tr -d '\r\n' < "$APP/.public-root-path")" = "$WEB"
test ! -e "$BACKUP/COMPLETE"
mapfile -t FILES < "$STAGE/deploy-tools/files.txt"
test "${#FILES[@]}" = 25
(cd "$STAGE" && sha256sum -c deploy-tools/manifest.sha256 >/dev/null)
mkdir -p "$BACKUP"
sha256sum "$APP/config/mysql.php" "$WEB/.backend-path" "$APP/.public-root-path" "$WEB/.htaccess" > "$BACKUP/protected-before.sha256"
for env in "$APP/.env" "$APP/.env.production" "$APP/.env.local" "$WEB/.env"; do if [[ -f "$env" ]]; then sha256sum "$env" >> "$BACKUP/protected-before.sha256"; fi; done
crontab -l > "$BACKUP/crontab-before.txt"
for file in "${FILES[@]}"; do
 case "$file" in dashboard-panel/admin/bootstrap.php|dashboard-panel/admin/crypto_quick.php|dashboard-panel/admin/customer_import.php|dashboard-panel/admin/customer_import_lib.php|dashboard-panel/admin/index.php|dashboard-panel/admin/locales/en.php|dashboard-panel/admin/locales/pl.php|dashboard-panel/admin/partials/crypto-create.php|dashboard-panel/admin/partials/customer-import-preview.php|dashboard-panel/admin/search.php|dashboard-panel/admin/views/crypto_quick.php|dashboard-panel/bootstrap/application.php|dashboard-panel/check_payment.php|dashboard-panel/locales/pl.php|dashboard-panel/pages/login.php|dashboard-panel/pages/profil/orders_payment.php|dashboard-panel/pages/profil/payments_crypto.php|dashboard-panel/templates/profil/orders_payment.tpl|dashboard-panel/templates/profil/payments_crypto.tpl|public_html/admin/customer_import.php|public_html/assets/css/admin.css|public_html/assets/js/admin-crypto-create.js|public_html/assets/js/admin-crypto-quick.js|public_html/assets/js/admin-customer-import.js|public_html/assets/js/admin.js) ;; *) exit 1;; esac
 if [[ "$file" == *.php ]]; then php -l "$STAGE/$file" >/dev/null; fi
 if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
 test ! -L "$target"
 expected=$(awk -v f="$file" '$2==f {print $1}' "$STAGE/deploy-tools/baseline.sha256")
 if [[ "$expected" == ABSENT ]]; then test ! -e "$target"; else
  test -f "$target"
  test "$(sha256sum "$target" | cut -d ' ' -f1)" = "$expected"
  mkdir -p "$BACKUP/files/$(dirname "$file")";cp -p "$target" "$BACKUP/files/$file"
 fi
done
php "$STAGE/deploy-tools/state.php" snapshot "$SITE" "$STAGE"
gzip -t "$BACKUP/database.sql.gz"
install_started=0
rollback(){
 rc=$?;trap - ERR
 if [[ "$install_started" == 1 ]]; then
  for file in "${FILES[@]}"; do
   if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
   if [[ -f "$BACKUP/files/$file" ]]; then cp -p "$BACKUP/files/$file" "$target"; else rm -f "$target"; fi
   rm -f "$target.customer-import.tmp"
  done
  echo "Previous runtime restored; database not changed." >&2
 fi
 exit "$rc"
}
trap rollback ERR
install_started=1
for file in "${FILES[@]}"; do
 if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
 mkdir -p "$(dirname "$target")"
 cp "$STAGE/$file" "$target.customer-import.tmp";chmod 644 "$target.customer-import.tmp"
done
# Install assets before their matching markup.
for file in "${FILES[@]}"; do
 if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
 if [[ -f "$target.customer-import.tmp" ]]; then mv -f "$target.customer-import.tmp" "$target"; fi
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
php "$STAGE/deploy-tools/state.php" verify "$SITE" "$STAGE"
printf '%s\n' "$(cat "$STAGE/deploy-tools/runtime-revision")" > "$BACKUP/runtime-revision"
touch "$BACKUP/COMPLETE"
printf 'DEPLOY_OK site=%s files=25 settings_and_data_preserved\n' "$SITE"
