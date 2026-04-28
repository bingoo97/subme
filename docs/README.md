# Dokumentacja Aplikacji

Ten katalog opisuje tylko aktualny sposob dzialania aplikacji, deployu i bazy danych.

Nie trzymamy tu juz planow historycznych, starych modeli multi-instance ani notatek o wersjach przejsciowych.

## Najwazniejsze zasady

### 1. Jedno runtime source of truth dla danych

Aplikacja podczas normalnej pracy korzysta z prawdziwej bazy MySQL.

- lokalnie:
  - `dashboard-panel/config/mysql.php`
  - Docker domyslnie laczy sie z baza `reseller_v2`
- na serwerze:
  - aktywny `mysql.php` z `~/.subme-secrets/<app-slug>/mysql.php`

Plik backupu SQL nie jest baza runtime.

### 2. Jedno source of truth dla schematu

Jedynym canonical source of truth dla schematu i seedow jest:

- `dashboard-panel/database/v2/`

Najwazniejsze pliki:

- `001_core_schema.sql`
- `002_seed_defaults.sql`
- `003_operational_views.sql`

Kazda zmiana tabel, kolumn, indeksow lub widokow musi byc zapisana w tym katalogu.

Dodatkowo dla juz istniejacych baz produkcyjnych trzymamy przyrostowe migracje tutaj:

- `dashboard-panel/database/migrations/`

### 3. Jeden panel, jedna baza, jedna glowna instancja

Aktualny kierunek projektu:

- jedna glowna instancja panelu
- jedna baza danych dla panelu
- osobny landing page bez SQL
- brak utrzymywania wielu subdomen z tym samym panelem jako modelu docelowego

### 4. Panel nie jest jeszcze gotowy pod subfolder typu `/app`

Kod nadal uzywa wielu sciezek absolutnych od `/`, dlatego:

- panel najlepiej wdrazac jako osobny webroot / vhost
- landing najlepiej trzymac jako osobny statyczny projekt

## Najwazniejsza funkcjonalnosc

Pelny opis jest tutaj:

- `docs/FUNCTIONALITY.md`

Najkrotsze podsumowanie:

- user kupuje subskrypcje lub doladowuje saldo
- admin zarzadza userami, produktami, zamowieniami, platnosciami i tresciami
- crypto i bank transfery sa obslugiwane recznie z panelu admina
- resellerzy i admini maja rozbudowany messenger z grupami i rozmowami `1 na 1`

## Portfele krypto

To jest aktualna i obowiazujaca logika systemu:

- klient nie musi miec portfela przypisanego recznie z gory
- jesli wybiera platnosc krypto i w puli jest wolny adres dla danego assetu, aplikacja przypisuje go automatycznie
- request platnosci zapisuje `wallet_assignment_id`
- jesli request zostanie anulowany i usuniety, assignment ma zostac zwolniony, a adres powinien wrocic na `available`

Kluczowe tabele:

- `crypto_wallet_addresses`
- `crypto_wallet_assignments`
- `crypto_deposit_requests`

Przy przenoszeniu bazy na inny serwer trzeba pilnowac spojnosci tych trzech tabel.

## Co jest tylko snapshotem, a nie source of truth

Ponizsze rzeczy nie sa glownym zrodlem prawdy:

- backup SQL pobrany z panelu admina
- export z phpMyAdmin
- lokalny stan bazy po testach
- produkcyjny stan bazy po klikaniu w panelu
- runtime helpery typu `app_ensure_*`

Backup SQL sluzy tylko do archiwizacji i awaryjnego restore.

## Aktualny workflow zmian DB

Kazda zmiana DB powinna isc w tej kolejnosci:

1. najpierw zmiana w `dashboard-panel/database/v2/`
2. potem nowy plik w `dashboard-panel/database/migrations/`, jesli zmiana ma wejsc na istniejacy serwer
3. potem zmiana w PHP
4. jesli trzeba, tymczasowy helper zgodnosci runtime
5. aktualizacja dokumentacji w `docs/`, jesli zmiana jest operacyjnie wazna

## Aktualny deploy

Najwazniejsze:

- `~/app` w starszych przykladach bylo tylko przykladem
- prawdziwa sciezka repo na serwerze musi istniec i zawierac `.git`
- w aktualnym skrypcie domyslny `REPO_DIR` to:
  - `~/subme`
- jesli chcesz uzyc innej nazwy katalogu, najpierw sklonuj tam repo i dopiero potem ustaw `REPO_DIR`

### Lokalnie

```bash
cd /Users/bodzianek/CascadeProjects/RESELLER/reseller
git add .
git commit -m "Opis zmian"
git push origin main
```

### Na serwerze

Jesli repo jest w domyslnym katalogu:

```bash
cd ~/subme
git pull --ff-only origin main
chmod +x ~/subme/docs/namecheap-*.sh
SITE_HOST=panel.twojadomena.pl APP_SLUG=main-panel WEB_DIR=~/panel-webroot REPO_DIR=~/subme APP_URL=https://panel.twojadomena.pl ~/subme/docs/namecheap-deploy.sh
```

Jesli repo ma byc w innym katalogu, np. `~/app`, najpierw musisz je tam sklonowac:

```bash
cd ~
git clone <URL-REPO> app
cd ~/app
git checkout main
chmod +x ~/app/docs/namecheap-*.sh
SITE_HOST=panel.twojadomena.pl APP_SLUG=main-panel WEB_DIR=~/panel-webroot REPO_DIR=~/app APP_URL=https://panel.twojadomena.pl ~/app/docs/namecheap-deploy.sh
```

Nie zadziala:

- `cd ~/app`, jesli taki katalog nie istnieje
- `git pull`, jesli nie jestes w katalogu repo
- `~/app/docs/namecheap-deploy.sh`, jesli repo nie zostalo tam sklonowane

Skrypt deployu wymaga:

- istniejacego katalogu repo z `.git`
- istniejacego `WEB_DIR`
- poprawnego `mysql.php` w `~/.subme-secrets/<app-slug>/mysql.php`

Jesli `WEB_DIR` jeszcze nie istnieje, utworz go przed pierwszym deployem:

```bash
mkdir -p ~/panel-webroot
```

Repo nie musi nazywac sie `subme`. W skryptach wazne sa:

- `REPO_DIR`
- `WEB_DIR`
- `SITE_HOST`
- `APP_SLUG`
- `APP_URL`

## Odbudowa bazy

Do czystej odbudowy schematu z repo uzywaj:

- `docs/namecheap-import-canonical-db.sh`

Ten skrypt bierze tylko:

- `001_core_schema.sql`
- `002_seed_defaults.sql`
- `003_operational_views.sql`

Backup z panelu admina sluzy tylko do restore konkretnego snapshotu:

- `docs/namecheap-import-db.sh`

Migracje przyrostowe dla istniejacych baz sa opisane tutaj:

- `dashboard-panel/database/MIGRATIONS.md`

## Gdy deploy nie dziala mimo poprawnych komend

Jesli:

- skrypt deployu przechodzi
- ale domena nadal nie pokazuje panelu
- albo widzisz redirect do strony hostingu

to sprawdz od razu:

- `docs/NEW_SERVER_CHECKLIST.md`

Szczegolnie sekcje:

- `14. Najczestszy blad przy deployu`
- `15. Gdy deploy "przeszedl", ale domena nadal nie dziala`

## Najwazniejsze pliki w tym katalogu

- `docs/FUNCTIONALITY.md`
- `docs/NEW_SERVER_CHECKLIST.md`
- `docs/LOCAL_DOCKER.md`
- `docs/00_SYSTEM_MAINTENANCE_RUNNER.md`
- `docs/00_EMAIL_NOTIFICATION_RULES.md`
- `docs/00_DATA_RETENTION_RULES.md`
- `docs/00_PROVIDER_DELIVERY_RULES.md`
- `docs/live-chat.md`
- `docs/namecheap-deploy.sh`
- `docs/namecheap-import-canonical-db.sh`
- `docs/namecheap-import-db.sh`
- `docs/namecheap-init-instance.sh`
- `docs/namecheap-list-instances.sh`
- `docs/namecheap-rollback.sh`
