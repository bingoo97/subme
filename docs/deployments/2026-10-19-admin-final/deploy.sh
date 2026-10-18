#!/usr/bin/env bash
set -euo pipefail
umask 077
SITE="$1"
case "$SITE:$USER" in demo:submtosl|dashboard:submtosl) ;; *) exit 1;; esac
STAGE="$HOME/.subme-deploy-stage/admin-final-20261019-01"
WEB="$HOME/$SITE.subme.pro"
APP="$HOME/.subme-apps/$SITE-subme-pro/dashboard-panel"
BACKUP="$HOME/.subme-deploy-backups/admin-final-20261019-01/$SITE"
test "$(tr -d '\r\n' < "$WEB/.backend-path")" = "$APP"
test "$(tr -d '\r\n' < "$APP/.public-root-path")" = "$WEB"
test ! -e "$BACKUP/COMPLETE"
mapfile -t FILES < "$STAGE/deploy-tools/files.txt"
test "${#FILES[@]}" = 39
(cd "$STAGE" && sha256sum -c deploy-tools/manifest.sha256 >/dev/null)
mkdir -p "$BACKUP"
sha256sum "$APP/config/mysql.php" "$WEB/.backend-path" "$APP/.public-root-path" "$WEB/.htaccess" > "$BACKUP/protected-before.sha256"
crontab -l > "$BACKUP/crontab-before.txt"
for file in "${FILES[@]}"; do
 case "$file" in dashboard-panel/config/mysql.php|*/uploads/*|*/sessions/*|*..*|/*) exit 1;; dashboard-panel/*|public_html/*) ;; *) exit 1;; esac
 if [[ "$file" == *.php ]]; then php -l "$STAGE/$file" >/dev/null; fi
 if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
 test ! -L "$target"
 expected=$(awk -v f="$file" '$2==f {print $1}' "$STAGE/deploy-tools/baseline.sha256")
 if test -f "$target"; then
  test "$(sha256sum "$target" | cut -d ' ' -f1)" = "$expected"
  mkdir -p "$BACKUP/files/$(dirname "$file")"; cp -p "$target" "$BACKUP/files/$file"
 else test "$expected" = NEW; printf '%s\n' "$file" >> "$BACKUP/created-files.txt"; fi
done
if test ! -f "$BACKUP/state-before.json"; then php "$STAGE/deploy-tools/state.php" snapshot "$SITE" "$STAGE"; fi
gzip -t "$BACKUP/database.sql.gz"
# Migrations only extend the current site's schema; no fixture/seed/customer data import.
php "$STAGE/deploy-tools/state.php" migrate "$SITE" "$STAGE"
install_started=0
rollback_code(){
 rc=$?; trap - ERR
 if [[ "$install_started" == 1 ]]; then
  for file in "${FILES[@]}"; do
   if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
   if test -f "$BACKUP/files/$file"; then cp -p "$BACKUP/files/$file" "$target"; else php -r 'if(is_file($argv[1]))unlink($argv[1]);' "$target"; fi
  done
  echo "Code rolled back; additive migrations retained. Private backup: $BACKUP" >&2
 fi
 exit "$rc"
}
trap rollback_code ERR
install_file(){
 local file="$1" target
 if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
 mkdir -p "$(dirname "$target")"; if [[ "$file" == public_html/* ]]; then chmod 755 "$(dirname "$target")"; fi; cp "$STAGE/$file" "$target.admin-final.tmp"; chmod 644 "$target.admin-final.tmp"; mv -f "$target.admin-final.tmp" "$target"
}
install_started=1
# New dependencies first; callers and public assets last. Each replacement is atomic.
for file in "${FILES[@]}"; do
 case "$file" in dashboard-panel/bootstrap/application.php|dashboard-panel/admin/bootstrap.php|dashboard-panel/admin/index.php|public_html/*) continue;; esac
 install_file "$file"
done
install_file dashboard-panel/bootstrap/application.php
install_file dashboard-panel/admin/bootstrap.php
install_file dashboard-panel/admin/index.php
for file in "${FILES[@]}"; do [[ "$file" != public_html/* ]] || install_file "$file"; done
php "$STAGE/deploy-tools/state.php" initialize "$SITE" "$STAGE"
for file in "${FILES[@]}"; do
 if [[ "$file" == dashboard-panel/* ]]; then target="$APP/${file#dashboard-panel/}"; else target="$WEB/${file#public_html/}"; fi
 test "$(sha256sum "$STAGE/$file" | cut -d ' ' -f1)" = "$(sha256sum "$target" | cut -d ' ' -f1)"
done
sha256sum -c "$BACKUP/protected-before.sha256"
crontab -l > "$BACKUP/crontab-after.txt"; cmp "$BACKUP/crontab-before.txt" "$BACKUP/crontab-after.txt"
php "$STAGE/deploy-tools/state.php" verify "$SITE" "$STAGE"
php "$STAGE/deploy-tools/probe.php" "$APP"
php "$STAGE/deploy-tools/http-check.php" "$SITE"
touch "$BACKUP/COMPLETE"
printf 'DEPLOY_OK site=%s runtime_files=39 provider_login=OFF tier_automation=OFF backup=%s\n' "$SITE" "$BACKUP"
