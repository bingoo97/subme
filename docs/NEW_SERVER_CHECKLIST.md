# New Server Checklist

Ten checklist zaklada docelowy model:

- jedna glowna instancja panelu
- jedna baza danych dla panelu
- osobny landing page bez SQL
- brak wielu subdomen z tym samym panelem

## 0. Wazna decyzja przed startem

Na dzis panel nie jest gotowy do pracy pod subfolderem typu:

- `https://twojadomena.pl/app`
- `https://twojadomena.pl/panel`

bo ma wiele sciezek absolutnych od `/`.

Dlatego na nowym serwerze rekomendowany model jest taki:

- landing page jako osobny statyczny projekt
- panel jako osobna aplikacja na osobnym webroot / vhost

Jesli chcesz miec wszystko pod jedna domena i jednym webrootem, trzeba bedzie pozniej zrobic refaktor pod `base path`.

## 1. Przygotowanie serwera

Sprawdz:

- dostep SSH
- PHP CLI
- MySQL
- `rsync`
- `git`

Przydatne komendy:

```bash
php -v
mysql --version
git --version
rsync --version
pwd
```

## 2. Przygotowanie katalogow

Najpierw wybierz jedna prawdziwa sciezke repo na serwerze.

Rekomendacja dla tego projektu:

- `~/subme`

Mozesz uzyc innej, np. `~/app`, ale tylko jesli faktycznie tam sklonujesz repo.

Przyklad docelowego ukladu dla domyslnego wariantu:

```text
~/subme                             -> repo z kodem
~/public_html                       -> webroot panelu albo webroot landingu
~/.subme-secrets/main-panel         -> mysql.php panelu
~/.subme-apps/main-panel            -> backend panelu
~/.subme-releases/main-panel        -> rollbacki panelu
```

Jesli landing i panel beda rozdzielone:

- landing dostaje swoj webroot
- panel dostaje swoj osobny webroot

## 3. Klon repo

Na nowym serwerze:

```bash
cd ~
git clone <URL-REPO> subme
cd ~/subme
git checkout main
```

Jesli chcesz miec repo w `~/app`, zrob to jawnie:

```bash
cd ~
git clone <URL-REPO> app
cd ~/app
git checkout main
```

Jesli repo juz istnieje, wejdz do jego realnej sciezki:

```bash
cd ~/subme
git pull --ff-only origin main
```

## 4. Baza danych

Utworz:

- jedna baze danych dla panelu
- jednego usera MySQL dla tej bazy

Nie tworz wielu baz dla wielu kopii tego samego panelu, jesli nie ma takiej potrzeby biznesowej.

## 5. mysql.php

Przygotuj plik:

```text
~/.subme-secrets/main-panel/mysql.php
```

To jest plik, z ktorego panel bedzie czytal prawdziwe polaczenie do DB.

Zasada:

- runtime panelu korzysta z MySQL
- nie z backupu SQL
- nie z phpMyAdmin exportu

## 6. Odbudowa bazy z canonical SQL

To jest rekomendowana sciezka startowa.

Uruchom:

```bash
cd ~/subme
chmod +x ~/subme/docs/namecheap-*.sh
DB_NAME=twoja_baza DB_USER=twoj_user CONFIRM_RESET=YES ~/subme/docs/namecheap-import-canonical-db.sh
```

To zaladuje tylko canonical schema source:

- `001_core_schema.sql`
- `002_seed_defaults.sql`
- `003_operational_views.sql`

## 7. Kiedy uzyc importu backupu SQL

Tylko gdy swiadomie chcesz odtworzyc snapshot danych.

Komenda:

```bash
DB_NAME=twoja_baza DB_USER=twoj_user ~/subme/docs/namecheap-import-db.sh /sciezka/do/backupu.sql
```

Nie uzywaj tej drogi jako standardowego deployu schematu.

## 7a. Jak aktualizowac tabele i kolumny na istniejacym serwerze

Jesli serwer juz dziala i chcesz dodac:

- nowa kolumne
- nowa tabele
- nowy indeks
- poprawke widoku

to nie rob pelnego resetu przez canonical import.

Zamiast tego:

1. dopisz zmiane do `dashboard-panel/database/v2/`
2. utworz nowy plik w `dashboard-panel/database/migrations/`
3. odpal ta migracje na produkcji

Przyklad:

```bash
cd ~/subme
mysql -u DB_USER -p DB_NAME < dashboard-panel/database/migrations/2026_04_28_001_add_example_column.sql
```

To jest wlasciwa droga dla istniejacej produkcji.

## 8. Pierwszy deploy panelu

Przyklad dla jednej glownej instancji panelu:

```bash
cd ~/subme
mkdir -p ~/panel-webroot
chmod +x ~/subme/docs/namecheap-*.sh
SITE_HOST=panel.twojadomena.pl \
APP_SLUG=main-panel \
WEB_DIR=~/panel-webroot \
REPO_DIR=~/subme \
APP_URL=https://panel.twojadomena.pl \
~/subme/docs/namecheap-init-instance.sh panel.twojadomena.pl
```

Potem deploy:

```bash
SITE_HOST=panel.twojadomena.pl \
APP_SLUG=main-panel \
WEB_DIR=~/panel-webroot \
REPO_DIR=~/subme \
APP_URL=https://panel.twojadomena.pl \
~/subme/docs/namecheap-deploy.sh
```

Jesli panel ma siedziec od razu w glownym webroot:

```bash
mkdir -p ~/public_html
SITE_HOST=twojadomena.pl \
APP_SLUG=main-panel \
WEB_DIR=~/public_html \
REPO_DIR=~/subme \
APP_URL=https://twojadomena.pl \
~/subme/docs/namecheap-deploy.sh
```

To jest technicznie mozliwe, ale wtedy landing nie moze byc osobnym statycznym projektem w tym samym miejscu.

## 9. Sprawdzenie po deployu

Sprawdz:

- czy strona glowna panelu sie otwiera
- czy `/admin/` dziala
- czy CSS/JS maja normalne `?v=...`, nie `?v=1`
- czy `config/mysql.php` jest obecny w aktywnym backendzie

Przydatne komendy:

```bash
cat ~/.subme-secrets/main-panel/mysql.php
cat ~/.subme-apps/main-panel/dashboard-panel/.public-root-path
```

## 10. Landing page bez SQL

Landing page trzymaj osobno od panelu:

- osobny katalog
- osobny webroot
- brak `mysql.php`
- brak backendu panelu

Najbezpieczniej:

- panel i landing rozdzielic juz na poziomie hostingu / vhosta / katalogu publikacji

## 11. Aktualizacje na przyszlosc

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
git pull --ff-only origin main
chmod +x ~/subme/docs/namecheap-*.sh
SITE_HOST=panel.twojadomena.pl APP_SLUG=main-panel WEB_DIR=~/panel-webroot REPO_DIR=~/subme APP_URL=https://panel.twojadomena.pl ~/subme/docs/namecheap-deploy.sh
```

Jesli uzywasz innego katalogu repo, zamien wszedzie `~/subme` na swoja realna sciezke, ale tylko po tym jak repo zostanie tam sklonowane.

## 12. Zasada source of truth

Pamietaj:

- source of truth dla schematu = `dashboard-panel/database/v2/`
- source of truth dla danych runtime = prawdziwa baza MySQL
- backup SQL z panelu admina = tylko snapshot restore

## 12a. Ważne dla portfeli krypto po przeniesieniu

Po przeniesieniu aplikacji na nowy serwer koniecznie sprawdz spojnosc tych 3 tabel:

- `crypto_wallet_addresses`
- `crypto_wallet_assignments`
- `crypto_deposit_requests`

System zaklada, ze:

- user moze dostac portfel automatycznie dopiero w chwili inicjacji platnosci
- request krypto wskazuje `wallet_assignment_id`
- usuniecie anulowanego requestu zwalnia assignment i przywraca adres do `available`

Po imporcie bazy zrob kontrolnie:

1. sprawdz, czy sa wolne adresy w `crypto_wallet_addresses`
2. sprawdz, czy nie ma assignmentow `active/released` bez sensownej relacji do requestow
3. sprawdz, czy anulowane requesty nie zostaly bez cleanupu assignmentu

Jesli cos wyglada podejrzanie, nie naprawiaj tego recznie przez samo kasowanie requestow. Najpierw sprawdz relacje:

- `crypto_deposit_requests.wallet_assignment_id`
- `crypto_wallet_assignments.wallet_address_id`
- `crypto_wallet_addresses.status`

## 13. Minimalna procedura przeniesienia na nowy serwer

Jesli chcesz przeniesc aplikacje jak najprosciej:

## 14. Najczestszy blad przy deployu

Jesli widzisz cos takiego:

- `cd: /home/.../app: No such file or directory`
- `fatal: not a git repository`
- `.../docs/namecheap-deploy.sh: No such file or directory`

to znaczy, ze:

- repo nie zostalo jeszcze sklonowane do tej sciezki
- albo jestes w zlym katalogu

Najpierw sprawdz:

```bash
cd ~
ls
```

Jesli widzisz `subme`, uzywaj:

```bash
cd ~/subme
git pull --ff-only origin main
chmod +x ~/subme/docs/namecheap-*.sh
```

Jesli chcesz uzywac `~/app`, najpierw zrob:

```bash
cd ~
git clone <URL-REPO> app
cd ~/app
```

Deploy uruchamiasz zawsze z realnej sciezki do repo i z poprawnym `REPO_DIR`.

## 15. Gdy deploy "przeszedl", ale domena nadal nie dziala

To jest osobny typ problemu. Skrypt moze wykonac sie poprawnie, ale sama domena dalej nie pokazuje panelu.

Najczestszy objaw:

- `curl -I https://panel.twojadomena.pl/`
- zwraca redirect do strony hostingu, np. Namecheap
- albo domena pokazuje pusty katalog / domyslna strone hostingu

To zwykle oznacza, ze problem nie jest w PHP ani w Git, tylko w konfiguracji hostingu.

### Co sprawdzic po kolei

1. Czy subdomena jest w ogole utworzona w panelu hostingu.
2. Czy document root tej subdomeny wskazuje na poprawny katalog, np.:
   - `~/panel-webroot`
3. Czy katalog webroot faktycznie istnieje:

```bash
ls -ld ~/panel-webroot
```

4. Czy istnieje katalog secrets dla danego `APP_SLUG`, np.:

```bash
ls -ld ~/.subme-secrets/main-panel
ls -l ~/.subme-secrets/main-panel/mysql.php
```

5. Czy backend instancji w ogole zostal utworzony:

```bash
ls -ld ~/.subme-apps/main-panel
ls -ld ~/.subme-apps/main-panel/dashboard-panel
```

### Typowy przypadek awarii

Jesli:

- nie ma `~/panel-webroot`
- nie ma `~/.subme-apps/main-panel`
- nie ma `~/.subme-secrets/main-panel/mysql.php`

to znaczy, ze nowa instancja nie zostala jeszcze przygotowana i sam deploy nie mial gdzie sie wdrozyc.

### Co wtedy zrobic

Utworz brakujace katalogi:

```bash
mkdir -p ~/panel-webroot
mkdir -p ~/.subme-secrets/main-panel
```

Wgraj prawidlowy plik:

```text
~/.subme-secrets/main-panel/mysql.php
```

Potem uruchom init i deploy:

```bash
cd ~/subme
chmod +x ~/subme/docs/namecheap-*.sh
SITE_HOST=panel.twojadomena.pl APP_SLUG=main-panel WEB_DIR=~/panel-webroot REPO_DIR=~/subme APP_URL=https://panel.twojadomena.pl ~/subme/docs/namecheap-init-instance.sh panel.twojadomena.pl
SITE_HOST=panel.twojadomena.pl APP_SLUG=main-panel WEB_DIR=~/panel-webroot REPO_DIR=~/subme APP_URL=https://panel.twojadomena.pl ~/subme/docs/namecheap-deploy.sh
```

### Szybki test po deployu

```bash
curl -kIs https://panel.twojadomena.pl/ | head
```

Jesli nadal widzisz redirect do hostingu zamiast swojej strony, to:

- subdomena nadal nie jest poprawnie podlaczona do webrootu
- albo DNS / konfiguracja hostingu jeszcze nie wskazuje na ta instancje

W takim przypadku najpierw popraw konfiguracje subdomeny w panelu hostingu, a dopiero potem powtarzaj deploy.

1. postaw nowy serwer
2. sklonuj repo do `~/app`
3. przygotuj baze i `mysql.php`
4. odbuduj DB z `namecheap-import-canonical-db.sh` albo odtworz snapshot
5. uruchom `namecheap-deploy.sh` z poprawnym `WEB_DIR`
6. sprawdz panel i admina

To jest docelowy, najprostszy model.
