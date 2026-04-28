# Single-Domain Deployment Plan

Ten dokument opisuje docelowy model wdrozenia na nowy serwer:

- bez wielu subdomen
- bez wielu kopii tego samego panelu
- z jedna baza danych dla panelu
- z osobnym landing page bez SQL

## Co chcemy osiagnac

Docelowo maja istniec dwa byty:

1. Landing page
   - statyczny
   - bez MySQL
   - bez backendu panelu
2. Panel aplikacji
   - jedna instancja
   - jedna baza danych
   - jeden backend
   - jeden zestaw sekretow i rollbackow

## Co jest mozliwe juz teraz

To repo jest gotowe do:

- deployu jednej instancji panelu na dowolny host
- deployu z dowolnego katalogu repo przez `REPO_DIR`
- deployu do dowolnego webroota przez `WEB_DIR`

Skrypty nie sa juz na sztywno przywiazane do:

- `~/subme`
- `dashboard.subme.pro`

## Czego jeszcze nie ma

Panel nie obsluguje jeszcze poprawnie instalacji pod subfolderem typu:

- `/app`
- `/panel`

Powod:

- duzo sciezek jest absolutnych od `/`
- assets, admin, uploads i endpointy pomocnicze zakladaja root domeny

## Wniosek praktyczny

Jesli chcesz miec:

- landing na `/`
- panel na `/app`

to trzeba bedzie zrobic osobny refaktor `base path`.

Na teraz najbezpieczniejsze opcje sa dwie.

### Opcja A

Panel zajmuje glowny webroot domeny:

- `WEB_DIR=~/public_html`

Wtedy wszystko dziala bez dodatkowego refaktoru, ale landing nie jest osobnym statycznym projektem.

### Opcja B

Landing i panel maja osobne webrooty / vhosty.

To jest najlepsza opcja operacyjna juz dzisiaj, jesli chcesz uniknac refaktoru panelu.

## Rekomendowany model na nowy serwer

Jesli priorytetem jest prostota kopiowania i pozniejszego utrzymania:

- repo: `~/app`
- panel webroot: `~/panel-webroot` albo inny katalog przypisany do panelu
- landing webroot: osobny katalog statyczny
- panel DB config: `~/.subme-secrets/main-panel/mysql.php`
- panel backend: `~/.subme-apps/main-panel/dashboard-panel`
- panel rollbacki: `~/.subme-releases/main-panel`

## Przyklad deployu pojedynczej instancji panelu

```bash
cd ~/app
git pull --ff-only origin main
chmod +x ~/app/docs/namecheap-*.sh
SITE_HOST=twojadomena.pl \
APP_SLUG=main-panel \
WEB_DIR=~/public_html \
REPO_DIR=~/app \
APP_URL=https://twojadomena.pl \
~/app/docs/namecheap-deploy.sh
```

## Przyklad odbudowy bazy z repo

```bash
cd ~/app
DB_NAME=twoja_baza DB_USER=twoj_user CONFIRM_RESET=YES ~/app/docs/namecheap-import-canonical-db.sh
```

## Zasada na przyszlosc

Jesli kolejny serwer ma byc prosty i przewidywalny:

- nie kopiujemy juz wielu subdomenowych instancji panelu
- nie traktujemy backupu z panelu admina jako source of truth
- schemat bazy trzymamy tylko w `dashboard-panel/database/v2/`
- landing i panel rozdzielamy odpowiedzialnoscia
