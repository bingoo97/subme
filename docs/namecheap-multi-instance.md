# Namecheap Multi-Instance Flow

Kazda subdomena ma osobna instancje:

- osobny web root, np. `~/dashboard.subme.pro`
- osobny backend, np. `~/.subme-apps/dashboard-subme-pro/dashboard-panel`
- osobny plik DB config, np. `~/.subme-secrets/dashboard-subme-pro/mysql.php`
- osobne snapshoty rollbacku, np. `~/.subme-releases/dashboard-subme-pro`

## Pierwsze przygotowanie nowej subdomeny

W cPanel:

1. dodaj subdomene
2. ustaw jej document root, np. `/home/submtosl/panel.subme.pro`
3. utworz osobna baze danych
4. utworz osobnego usera MySQL
5. przypisz usera do bazy

Na serwerze:

```bash
cd ~/subme
git pull
chmod +x ~/subme/docs/namecheap-*.sh
~/subme/docs/namecheap-init-instance.sh panel.subme.pro
nano ~/.subme-secrets/panel-subme-pro/mysql.php
SUBDOMAIN=panel.subme.pro APP_SLUG=panel-subme-pro ~/subme/docs/namecheap-deploy.sh
```

## Aktualizacja konkretnej subdomeny

```bash
cd ~/subme
git pull
SUBDOMAIN=panel.subme.pro APP_SLUG=panel-subme-pro ~/subme/docs/namecheap-deploy.sh
```

## Rollback konkretnej subdomeny

```bash
SUBDOMAIN=panel.subme.pro APP_SLUG=panel-subme-pro ~/subme/docs/namecheap-rollback.sh
```

## Import bazy danych

Canonical bootstrap z repo:

```bash
DB_NAME=twoja_baza DB_USER=twoj_user CONFIRM_RESET=YES ~/subme/docs/namecheap-import-canonical-db.sh
```

Import snapshotu / backupu SQL:

```bash
DB_NAME=twoja_baza DB_USER=twoj_user ~/subme/docs/namecheap-import-db.sh /home/submtosl/RESELLER/reseller_v2_namecheap.sql
```

Zasada:

- `namecheap-import-canonical-db.sh` jest droga do odbudowy bazy z repo
- `namecheap-import-db.sh` jest droga do odtworzenia konkretnego snapshotu / backupu

## Lista instancji

```bash
~/subme/docs/namecheap-list-instances.sh
```
