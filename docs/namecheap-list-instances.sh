#!/bin/bash
set -euo pipefail

APP_HOME="${HOME}"
BASE_DIR="${APP_HOME}/.subme-secrets"

if [ ! -d "${BASE_DIR}" ]; then
  printf 'No instances configured yet.\n'
  exit 0
fi

for dir in "${BASE_DIR}"/*; do
  [ -d "${dir}" ] || continue

  slug="$(basename "${dir}")"
  subdomain_guess="$(printf '%s' "${slug}" | tr '-' '.')"
  app_dir="${APP_HOME}/.subme-apps/${slug}/dashboard-panel"
  web_dir=""

  while IFS= read -r candidate; do
    [ -f "${candidate}/.backend-path" ] || continue
    pointer="$(cat "${candidate}/.backend-path" 2>/dev/null || true)"
    if [ "${pointer}" = "${app_dir}" ]; then
      web_dir="${candidate}"
      subdomain_guess="$(basename "${candidate}")"
      break
    fi
  done < <(find "${APP_HOME}" -maxdepth 1 -mindepth 1 -type d)

  printf '%s\n' "Subdomain: ${subdomain_guess}"
  printf '%s\n' "  slug: ${slug}"
  printf '%s\n' "  web: ${web_dir:-unknown}"
  printf '%s\n' "  app: ${app_dir}"
  printf '%s\n' "  mysql: ${dir}/mysql.php"
done
