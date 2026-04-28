# Deployment And Database Source Of Truth

Ten plik jest glownym zrodlem prawdy dla deployu i pracy z baza danych w tym repo.

## Co aplikacja naprawde wykorzystuje

Aplikacja nie czyta danych z pliku `.sql` podczas normalnej pracy.

W runtime zawsze korzysta z prawdziwej bazy MySQL:

- lokalnie:
  - `dashboard-panel/config/mysql.php`
  - w Dockerze domyslnie:
    - `DB_HOST=db`
    - `DB_NAME=reseller_v2`
    - `DB_USER=marbodz_reseller`
- na serwerze:
  - instancjowy plik `mysql.php` z katalogu `~/.subme-secrets/<app-slug>/mysql.php`
  - po deployu ten config trafia do aktywnego backendu

To oznacza:

- `localhost` nie korzysta z backupu SQL jako bazy runtime
- produkcja nie korzysta z backupu SQL jako bazy runtime
- backup SQL jest tylko snapshotem do odtworzenia danych, nie aktywna baza

## Jedno zrodlo prawdy dla schematu

Jedynym canonical source of truth dla schematu i seedow jest katalog:

- `dashboard-panel/database/v2/`

Najwazniejsze pliki:

- `dashboard-panel/database/v2/001_core_schema.sql`
  - tabele, kolumny, klucze, indeksy
- `dashboard-panel/database/v2/002_seed_defaults.sql`
  - ustawienia startowe, role, nawigacja, seedy
- `dashboard-panel/database/v2/003_operational_views.sql`
  - widoki operacyjne

## Czego NIE traktujemy jako source of truth

Ponizsze rzeczy nie sa glownym zrodlem prawdy:

- backup SQL pobrany z panelu admina
- reczny export z phpMyAdmin
- aktualny stan lokalnej bazy po testach
- aktualny stan produkcyjnej bazy po klikaniu w panelu
- same funkcje runtime typu `app_ensure_*` albo `chat_ensure_*`
- sam katalog `dashboard-panel/migrations/`

Wazne:

- `dashboard-panel/migrations/` nie jest uruchamiany automatycznie w standardowym deployu Namecheap
- jesli dodasz migracje, to ta sama zmiana i tak musi byc zapisana w `dashboard-panel/database/v2/`
- helpery runtime w PHP sa tylko warstwa zgodnosci / safety net dla dzialajacych instancji

## Zasada na przyszlosc

Kazda zmiana DB ma isc zawsze w tej kolejnosci:

1. Najpierw zmieniasz SQL w `dashboard-panel/database/v2/`.
2. Potem dopiero dopinasz kod PHP korzystajacy z tej zmiany.
3. Jesli trzeba utrzymac zgodnosc ze stara, juz dzialajaca baza, mozesz dodac tymczasowy runtime helper `ALTER TABLE` / `CREATE TABLE`.
4. Ale ten helper nigdy nie zastępuje wpisu w `dashboard-panel/database/v2/`.
5. Jesli zmiana dotyczy deployu albo procesu DB, aktualizujesz tez ten plik `docs/README.md`.

## Normalny deploy kodu

Na Macu:

```bash
cd /Users/bodzianek/CascadeProjects/RESELLER/reseller
git add .
git commit -m "Opis zmian"
git push origin main
```

Na serwerze:

```bash
cd ~/subme
git stash push -m "docs-before-pull" -- docs/namecheap-deploy.sh docs/namecheap-rollback.sh docs/namecheap-import-db.sh docs/namecheap-import-canonical-db.sh docs/namecheap-init-instance.sh docs/namecheap-list-instances.sh || true
git pull --ff-only origin main
chmod +x ~/subme/docs/namecheap-*.sh
SITE_HOST=dashboard.subme.pro APP_SLUG=dashboard-subme-pro WEB_DIR=~/dashboard.subme.pro REPO_DIR=~/subme ~/subme/docs/namecheap-deploy.sh
```

Repo nie musi nazywac sie `subme`.

Mozesz trzymac kod np. w:

- `~/app`
- `~/reseller`
- `~/project`

Wtedy po prostu podajesz:

- `REPO_DIR=~/app`

## Model docelowy na nowy serwer

Docelowo chcesz miec:

- glowna domene
- landing page bez SQL
- jeden panel aplikacji
- zero klonow tej samej aplikacji na wiele subdomen i wiele baz

To jest dobry kierunek i zapisujemy go jako nowa zasade.

### Nowa zasada architektoniczna

Na nowym serwerze zakladamy:

- jedna glowna aplikacja panelowa
- jedna baza danych dla tej aplikacji
- jeden katalog secrets dla tej aplikacji
- jeden katalog rollbackow dla tej aplikacji
- osobny landing statyczny bez polaczenia z MySQL

### Bardzo wazne ograniczenie techniczne

Obecny panel nie jest jeszcze gotowy do pracy pod subsciezka typu:

- `https://twojadomena.pl/app`
- `https://twojadomena.pl/panel`

bo obecny kod ma wiele sciezek absolutnych typu:

- `/admin/`
- `/assets/`
- `/uploads/`
- `/check.php`

To oznacza:

- jesli panel ma dzialac bez subdomeny, najbezpieczniej dzisiaj uruchomic go jako glowna aplikacje w glownym webroot
- jesli strona glowna ma byc osobnym landing page bez SQL, to wymaga to osobnego webroota albo przyszlego refaktoru aplikacji pod base-path

### Co rekomenduje na teraz

Najbezpieczniejsza sciezka do latwego przeniesienia na nowy serwer:

1. repo aplikacji trzymaj w jednym katalogu, np. `~/app`
2. panel aplikacji deployuj jednym skryptem z `REPO_DIR`, `SITE_HOST`, `WEB_DIR`, `APP_SLUG`
3. landing page trzymaj jako osobny statyczny projekt
4. nie mieszaj landingu i panelu w jednym webroot, dopoki panel nie dostanie obslugi `base path`

## Jeden model kopiowania na nowy serwer

Docelowo chcesz miec mozliwosc skopiowania aplikacji w prosty sposob. Ten model ma byc taki:

- repo z kodem: dowolny katalog, np. `~/app`
- webroot panelu: np. `~/public_html` albo inny wskazany przez `WEB_DIR`
- backend aplikacji: `~/.subme-apps/<app-slug>/dashboard-panel`
- config bazy: `~/.subme-secrets/<app-slug>/mysql.php`
- rollbacki: `~/.subme-releases/<app-slug>`

W praktyce do uruchomienia na nowym serwerze wystarczy:

1. skopiowac repo
2. ustawic `mysql.php`
3. odbudowac baze canonical SQL albo odtworzyc snapshot
4. uruchomic deploy z prawidlowym `WEB_DIR`

## Jak odbudowac baze z canonical SQL z repo

Do czystej odbudowy schematu z repo uzywaj:

- `docs/namecheap-import-canonical-db.sh`

Ten skrypt bierze tylko canonical SQL z:

- `001_core_schema.sql`
- `002_seed_defaults.sql`
- `003_operational_views.sql`

czyli dokladnie z jednego miejsca, a nie z backupu pobranego z panelu.

Przyklad:

```bash
cd ~/subme
DB_NAME=twoja_baza DB_USER=twoj_user CONFIRM_RESET=YES ~/subme/docs/namecheap-import-canonical-db.sh
```

## Do czego sluzy backup SQL z panelu admina

Przycisk `Pobierz backup SQL` w `Settings` sluzy tylko do:

- awaryjnego backupu
- archiwizacji
- przywrocenia calego snapshotu, jesli swiadomie chcesz odtworzyc stan z danego dnia

Nie sluzy do:

- wdrazania nowych kolumn
- synchronizacji schematu miedzy localhost a serwerem
- ustalania, co jest aktualna wersja bazy

## Auto-przypisanie portfeli krypto

To jest teraz czesc docelowej logiki systemu i trzeba to traktowac jako stala zasade przy deployu i migracjach.

Jesli klient inicjuje platnosc krypto i:

- nie ma jeszcze przypisanego portfela dla wybranego assetu
- ale w puli istnieje wolny adres tej kryptowaluty

to aplikacja automatycznie:

- pokazuje te kryptowalute w UI usera
- wybiera wolny adres z puli
- tworzy `crypto_wallet_assignments`
- tworzy request platnosci powiazany z `wallet_assignment_id`

To oznacza:

- admin nie musi recznie przypisywac portfela kazdemu nowemu userowi
- kluczowe tabele dla tego flow to:
  - `crypto_wallet_addresses`
  - `crypto_wallet_assignments`
  - `crypto_deposit_requests`

## Zwolnienie portfela po anulowaniu lub usunieciu requestu

Jesli request krypto zostanie:

- anulowany i potem skasowany przez admina z `Payments`
- usuniety zbiorczo przez `Usuń anulowane`
- usuniety przez klienta w top-up krypto

to aplikacja ma zwolnic powiazany `wallet_assignment_id`, a status adresu powinien wrocic na:

- `available`

To jest wazne przy przenoszeniu aplikacji na inny serwer, bo po imporcie bazy:

- assignmenty i requesty musza pozostac spojne
- nie wolno recznie kasowac tylko samych requestow bez uwzglednienia assignmentow

Jesli odtwarzasz snapshot bazy i cos wyglada zle w puli portfeli, najpierw sprawdz:

- czy `crypto_deposit_requests.wallet_assignment_id` zgadza sie z `crypto_wallet_assignments.id`
- czy adresy w `crypto_wallet_addresses` nie zostaly na stale w statusie `assigned` bez aktywnego assignmentu

## Szybka odpowiedz na Twoje pytanie

Jesli pytanie brzmi:

- "z czego aplikacja korzysta na localhost?"

to odpowiedz brzmi:

- z MySQL `reseller_v2`, nie z pliku `.sql`

Jesli pytanie brzmi:

- "z czego produkcja korzysta na serwerze?"

to odpowiedz brzmi:

- z instancyjnej bazy MySQL wskazanej przez `mysql.php`, nie z pliku `.sql`

Jesli pytanie brzmi:

- "jaki plik jest zrodlem prawdy dla schematu?"

to odpowiedz brzmi:

- `dashboard-panel/database/v2/`

## Powiazane pliki

- `docs/NEW_SERVER_CHECKLIST.md`
- `docs/namecheap-import-canonical-db.sh`
- `docs/namecheap-import-db.sh`
- `docs/namecheap-multi-instance.md`
- `dashboard-panel/docs/00_DATABASE_RUNTIME_SOURCE_OF_TRUTH.md`
- `dashboard-panel/database/v2/README.md`
