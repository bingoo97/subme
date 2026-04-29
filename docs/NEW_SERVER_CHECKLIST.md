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

Jesli repo nie jest sklonowane do `~/subme`, podmien ta sciezke na swoj realny katalog repo, np. `~/app/docs/namecheap-import-db.sh`.

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

## 12. Pelny przyklad 1:1 dla zoki.pro na root domeny

To jest realny przyklad wdrozenia panelu bez subdomeny, bez subfolderu, bezposrednio do:

- `https://zoki.pro`
- webroot: `~/public_html`
- repo na serwerze: `~/subme`
- app slug: `main-panel`

To jest instrukcja "dla laika", czyli:

- co przygotowac
- co wkleic po kolei do terminala
- co kliknac w `phpMyAdmin`
- co ustawic w DNS
- jak sprawdzic, czy wszystko rzeczywiscie dziala

### 12.0. Co musisz miec zanim zaczniesz

Przed startem przygotuj:

- dane SSH do nowego serwera
- nazwe bazy MySQL, np. `zokiscya_reseller`
- nazwe usera MySQL, np. `zokiscya_reseller`
- haslo do tego usera MySQL
- dostep do `phpMyAdmin` na starym serwerze albo gotowy plik backupu `.sql`
- dostep do panelu DNS domeny `zoki.pro` w Namecheap

W tym konkretnym wdrozeniu finalnie bylo tak:

- domena: `zoki.pro`
- konto SSH: `zokiscya`
- serwer: `198.54.114.134`
- port SSH: `21098`
- repo na serwerze: `~/subme`
- produkcyjny webroot: `~/public_html`
- backend aplikacji: `~/.subme-apps/main-panel/dashboard-panel`
- config bazy: `~/.subme-secrets/main-panel/mysql.php`

### 12.0a. Co oznacza "instalacja na root domeny"

To znaczy, ze panel nie dziala pod:

- `https://zoki.pro/panel`
- `https://panel.zoki.pro`

tylko bezposrednio pod:

- `https://zoki.pro`
- `https://zoki.pro/admin/`

W praktyce oznacza to, ze wdrazasz aplikacje do:

```text
~/public_html
```

a nie do dodatkowego katalogu typu:

```text
~/panel-webroot
```

### 12.1. Logowanie na serwer

Najpierw zaloguj sie po SSH:

```bash
ssh -p 21098 zokiscya@198.54.114.134
```

Po zalogowaniu warto sprawdzic, gdzie jestes i co masz w katalogu domowym:

```bash
pwd
cd ~
ls
```

To jest przydatne, bo wiele bledow bierze sie z tego, ze repo nie jest tam, gdzie myslisz.

### 12.2. Klon repo i przygotowanie skryptow

Jesli repo nie jest jeszcze wgrane na serwer, sklonuj je:

```bash
cd ~
git clone https://github.com/bingoo97/subme.git subme
cd ~/subme
git checkout main
chmod +x ~/subme/docs/namecheap-*.sh
```

Co robia te komendy:

- `git clone ... subme` pobiera repo do katalogu `~/subme`
- `git checkout main` ustawia glowna galaz
- `chmod +x ...` nadaje prawa do uruchamiania skryptom deployowym

Jesli repo juz istnieje:

```bash
cd ~/subme
git pull --ff-only origin main
chmod +x ~/subme/docs/namecheap-*.sh
```

Jesli nie jestes pewien, czy repo juz istnieje:

```bash
cd ~
ls
```

Jesli widzisz `subme`, to repo juz jest.

### 12.2a. Najczestszy blad na tym etapie

Jesli wpiszesz cos w stylu:

```bash
git clone <URL-REPO> subme
```

to nie zadziala, bo `<URL-REPO>` to tylko placeholder z dokumentacji.

Musisz podac prawdziwy adres repo, np.:

```bash
git clone https://github.com/bingoo97/subme.git subme
```

### 12.3. Import bazy danych

Sa 2 poprawne drogi:

1. odtwarzasz prawdziwa baze z backupu SQL
2. stawiasz czysta baze startowa z repo

W tym wdrozeniu dla `zoki.pro` baza zostala wgrana recznie przez `phpMyAdmin`, wiec nie byl uzyty import przez shell.

#### 12.3a. Wariant A: masz stara baze i chcesz ja przeniesc 1:1

Na starym serwerze w `phpMyAdmin`:

1. wybierz baze
2. kliknij `Export`
3. wybierz `Quick` albo `Custom`
4. format: `SQL`
5. pobierz plik `.sql`

Potem masz 2 opcje:

- wgrac ten plik przez `phpMyAdmin` na nowym serwerze
- wrzucic plik na serwer i zaimportowac przez shell

Jesli chcesz sprawdzic, czy backup jest juz na serwerze:

```bash
find ~ -type f -name "*.sql"
find ~ -type f \( -name "*.sql" -o -name "*.sql.gz" \)
```

Jesli backup lezalby np. w katalogu domowym jako `backup.sql`, import przez shell wyglada tak:

```bash
DB_NAME=zokiscya_reseller DB_USER=zokiscya_reseller ~/subme/docs/namecheap-import-db.sh ~/backup.sql
```

Jesli masz plik skompresowany `.sql.gz`, najpierw go rozpakuj:

```bash
gzip -dk ~/backup.sql.gz
```

a potem importuj:

```bash
DB_NAME=zokiscya_reseller DB_USER=zokiscya_reseller ~/subme/docs/namecheap-import-db.sh ~/backup.sql
```

#### 12.3b. Wariant B: nie przenosisz starych danych, tylko chcesz czysta baze

Jesli nie chcesz przenosic starych kont, produktow, zamowien i ustawien, tylko postawic czysta baze startowa z repo, uzyj:

```bash
DB_NAME=zokiscya_reseller DB_USER=zokiscya_reseller CONFIRM_RESET=YES ~/subme/docs/namecheap-import-canonical-db.sh
```

Ta komenda:

- wgrywa canonical schema
- tworzy czysta baze startowa
- nie przywraca Twoich starych danych z produkcji

#### 12.3c. Jak bylo zrobione dla zoki.pro

Dla `zoki.pro` finalnie:

- repo zostalo sklonowane na serwer
- baza zostala wgrana recznie przez `phpMyAdmin`
- potem aplikacja zostala podlaczona do tej bazy przez `mysql.php`

Czyli jesli chcesz miec kopie 1:1 starej strony, najbezpieczniej:

1. eksport ze starego `phpMyAdmin`
2. import do nowego `phpMyAdmin`
3. ustawienie poprawnego `mysql.php`
4. deploy panelu

### 12.3d. O czym latwo zapomniec przy bazie

Sama baza i sam user MySQL to za malo. User musi miec jeszcze prawa do tej bazy.

Jesli panel po deployu pokazuje blad typu:

- `No database`
- brak polaczenia z baza
- biala strona po logowaniu

to czesto znaczy, ze:

- haslo w `mysql.php` jest zle
- albo user nie ma uprawnien do tej bazy

W realnym wdrozeniu `zoki.pro` trzeba bylo jeszcze dopiac uprawnienia usera do bazy, bo sama baza istniala, ale user nie mial odpowiednich praw.

### 12.4. Inicjalizacja instancji na root domeny

To jest wariant bez subdomeny, bez `~/panel-webroot`, bez osobnego katalogu publikacji.

Najpierw utworz webroot:

```bash
cd ~/subme
mkdir -p ~/public_html
SITE_HOST=zoki.pro APP_SLUG=main-panel WEB_DIR=~/public_html REPO_DIR=~/subme APP_URL=https://zoki.pro ~/subme/docs/namecheap-init-instance.sh zoki.pro
```

Co robi ta komenda:

- przygotowuje instancje o slug `main-panel`
- podpina ja do domeny `zoki.pro`
- ustawia katalog publikacji na `~/public_html`
- przygotowuje backend w `~/.subme-apps/main-panel`
- przygotowuje katalog secrets w `~/.subme-secrets/main-panel`

Jesli komenda przejdzie, to znaczy, ze struktura instancji jest gotowa, ale strona jeszcze nie musi dzialac, bo przed nami nadal jest konfiguracja bazy i deploy.

### 12.5. Konfiguracja MySQL dla panelu

Teraz musisz podac aplikacji prawdziwe dane do MySQL.

Wazne:

- nie podmieniasz calego pliku `mysql.php` na 4 linie
- edytujesz istniejacy plik wygenerowany przez `namecheap-init-instance.sh`
- w tym pliku zmieniasz tylko domyslne wartosci dla `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`

Otworz plik:

```bash
nano ~/.subme-secrets/main-panel/mysql.php
```

Znajdz w nim taki fragment:

```php
		$db_host = getenv("DB_HOST") ?: "localhost";
		$db_name = getenv("DB_NAME") ?: "zokiscya_reseller";
		$db_user = getenv("DB_USER") ?: "zokiscya_reseller";
		$db_pass = getenv("DB_PASS") ?: "TUTAJ_WPISZ_HASLO_DO_BAZY";
```

Podmien:

- `TUTAJ_WPISZ_HASLO_DO_BAZY`

na prawdziwe haslo usera MySQL.

Jesli baza jest na tym samym hostingu, zwykle:

- host to `localhost`

Jak zapisac plik w `nano`:

1. `Ctrl + O`
2. `Enter`
3. `Ctrl + X`

Po zapisaniu mozesz sprawdzic, czy plik istnieje:

```bash
ls -l ~/.subme-secrets/main-panel/mysql.php
```

Mozesz tez sprawdzic, czy plik ma juz poprawne wartosci:

```bash
rg -n "db_host|db_name|db_user|db_pass" ~/.subme-secrets/main-panel/mysql.php
```

#### 12.5a. Dlaczego ten plik jest taki wazny

To jest najwazniejszy plik laczacy aplikacje z baza.

Jesli:

- domena dziala
- pliki sa wdrozone
- ale panel nie laczy sie z baza

to prawie zawsze problem siedzi w:

- `mysql.php`
- albo w samych prawach usera MySQL
- albo w zlej nazwie bazy / usera / hasla

### 12.6. Deploy na glowny root domeny

Teraz dopiero robisz wlasciwe wdrozenie:

```bash
SITE_HOST=zoki.pro APP_SLUG=main-panel WEB_DIR=~/public_html REPO_DIR=~/subme APP_URL=https://zoki.pro ~/subme/docs/namecheap-deploy.sh
```

Co robi deploy:

- kopiuje publiczne pliki do `~/public_html`
- kopiuje backend do `~/.subme-apps/main-panel/dashboard-panel`
- przygotowuje aktywna wersje aplikacji
- tworzy snapshot rollbacku

Jesli wszystko jest ok, zobaczysz komunikat o zakonczeniu deployu i adres strony.

### 12.7. Kontrola po deployu

Po deployu sprawdz:

```bash
rg -n "db_host|db_name|db_user|db_pass" ~/.subme-secrets/main-panel/mysql.php
cat ~/.subme-apps/main-panel/dashboard-panel/.public-root-path
ls -l ~/public_html/index.php
cat ~/public_html/.backend-path
curl -I --max-time 15 http://zoki.pro/
curl -I --max-time 15 https://zoki.pro/
curl -I --max-time 15 https://zoki.pro/admin/
```

Co oznaczaja te komendy:

- `rg -n "db_host|db_name|db_user|db_pass" ~/.subme-secrets/main-panel/mysql.php` pokazuje, czy config bazy jest na miejscu i czy ma poprawne wartosci
- `cat ~/.subme-apps/main-panel/dashboard-panel/.public-root-path` pokazuje, jaki webroot ma aktywna instancja
- `ls -l ~/public_html/index.php` sprawdza, czy plik startowy strony w ogole zostal wdrozony
- `cat ~/public_html/.backend-path` pokazuje, na jaki backend wskazuje katalog publiczny
- `curl -I ...` sprawdza, czy domena odpowiada

Jesli `curl` na `https://zoki.pro/` timeoutuje, a deploy byl poprawny, to zwykle problemem nie jest aplikacja, tylko DNS albo SSL.

### 12.8. DNS dla root domeny w Namecheap

Dla `zoki.pro` finalnie potrzebne byly takie rekordy:

```text
A Record      @      198.54.114.134
CNAME Record  www    zoki.pro
```

Trzeba usunac stare rekordy typu:

- `URL Redirect Record` dla `@`
- `CNAME www -> parkingpage.namecheap.com`

To bardzo wazne:

- nie moze zostac przekierowanie Namecheap
- nie moze zostac parking Namecheap

Jesli te stare rekordy zostana, domena nie pokaze Twojej strony, nawet jesli deploy i baza sa poprawne.

Do sprawdzenia propagacji:

```bash
dig zoki.pro @8.8.8.8 +short
dig www.zoki.pro @8.8.8.8 +short
curl -I --resolve zoki.pro:80:198.54.114.134 http://zoki.pro/
curl -I --resolve zoki.pro:443:198.54.114.134 https://zoki.pro/
curl -I --resolve zoki.pro:443:198.54.114.134 https://zoki.pro/admin/
```

Jesli zobaczysz:

- `198.54.114.134`

to DNS juz wskazuje na poprawny serwer.

### 12.8a. SSL dla zoki.pro

Po ustawieniu DNS trzeba jeszcze miec poprawny certyfikat SSL dla:

- `zoki.pro`
- `www.zoki.pro`

W praktyce na tym wdrozeniu:

- HTTP zaczelo dzialac najpierw
- HTTPS poczatkowo nie dzialalo, bo serwer pokazywal zly certyfikat
- dopiero po instalacji SSL domena zaczela dzialac poprawnie po `https://`

Po instalacji SSL warto sprawdzic:

```bash
curl -I http://zoki.pro/
curl -I https://zoki.pro/
curl -I https://zoki.pro/admin/
```

Docelowo:

- `http://zoki.pro/` moze dawac `301` lub `302` do HTTPS
- `https://zoki.pro/` powinno dawac `200`
- `https://zoki.pro/admin/` powinno dawac `200`

### 12.9. Cron dla production

Na `zoki.pro` zostal dodany taki cron:

```cron
MAILTO="support@zoki.pro"
*/5 * * * * /usr/local/bin/php /home/zokiscya/.subme-apps/main-panel/dashboard-panel/scripts/system_maintenance.php >/dev/null 2>&1
```

To jest cron, ktory wykonuje maintenance aplikacji co 5 minut.

Jesli dodajesz go w `crontab -e`, wklejasz cala linie razem z `*/5 * * * *`.

Jesli dodajesz go przez formularz `Cron Jobs` w cPanel:

- czasy ustawiasz w polach formularza
- w pole `Command` wklejasz tylko:

```bash
/usr/local/bin/php /home/zokiscya/.subme-apps/main-panel/dashboard-panel/scripts/system_maintenance.php >/dev/null 2>&1
```

Do testu recznego:

```bash
/usr/local/bin/php /home/zokiscya/.subme-apps/main-panel/dashboard-panel/scripts/system_maintenance.php
```

Jesli skrypt dziala poprawnie, powinien zakonczyc sie bez bledu.

### 12.10. Kolejny deploy po zmianach w kodzie

Lokalnie na Macu:

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
SITE_HOST=zoki.pro APP_SLUG=main-panel WEB_DIR=~/public_html REPO_DIR=~/subme APP_URL=https://zoki.pro ~/subme/docs/namecheap-deploy.sh
```

To jest juz normalny cykl aktualizacji:

1. zmieniasz kod lokalnie
2. robisz `git push`
3. na serwerze robisz `git pull`
4. odpalasz deploy

Wazne:

- sam `namecheap-deploy.sh` nie wykonuje automatycznie nowych zmian SQL
- jesli release dodaje nowa tabele, kolumne albo indeks, musisz jeszcze odpalic odpowiedni plik SQL na produkcji
- ogolna zasada jest opisana wyzej w sekcji `7a. Jak aktualizowac tabele i kolumny na istniejacym serwerze`

### 12.11. Szybka wersja: wszystkie komendy po kolei

Jesli masz juz:

- dzialajacy DNS
- utworzona baze
- usera MySQL
- haslo do bazy
- backup SQL albo czysta baze

to najkrotsza wersja wyglada tak:

```bash
ssh -p 21098 zokiscya@198.54.114.134
cd ~
git clone https://github.com/bingoo97/subme.git subme
cd ~/subme
git checkout main
chmod +x ~/subme/docs/namecheap-*.sh
mkdir -p ~/public_html
SITE_HOST=zoki.pro APP_SLUG=main-panel WEB_DIR=~/public_html REPO_DIR=~/subme APP_URL=https://zoki.pro ~/subme/docs/namecheap-init-instance.sh zoki.pro
nano ~/.subme-secrets/main-panel/mysql.php
SITE_HOST=zoki.pro APP_SLUG=main-panel WEB_DIR=~/public_html REPO_DIR=~/subme APP_URL=https://zoki.pro ~/subme/docs/namecheap-deploy.sh
cat ~/.subme-apps/main-panel/dashboard-panel/.public-root-path
curl -I --max-time 15 http://zoki.pro/
curl -I --max-time 15 https://zoki.pro/
curl -I --max-time 15 https://zoki.pro/admin/
```

Jesli importujesz backup SQL przez shell, dorzucasz przed deployem:

```bash
DB_NAME=zokiscya_reseller DB_USER=zokiscya_reseller ~/subme/docs/namecheap-import-db.sh ~/backup.sql
```

Jesli stawiasz czysta baze canonical, zamiast backupu:

```bash
DB_NAME=zokiscya_reseller DB_USER=zokiscya_reseller CONFIRM_RESET=YES ~/subme/docs/namecheap-import-canonical-db.sh
```

### 12.12. Jak rozpoznac, ze wdrozenie 1:1 jest gotowe

Wdrozenie mozna uznac za zakonczone dopiero wtedy, gdy jednoczesnie:

- `https://zoki.pro/` otwiera frontend
- `https://zoki.pro/admin/` otwiera panel
- panel loguje sie do bazy bez bledu
- CSS i JS laduja sie poprawnie
- cron dziala
- SSL jest poprawny
- DNS wskazuje na nowy serwer

Jesli choc jeden z tych punktow nie dziala, to znaczy, ze migracja nie jest jeszcze domknieta.

## 13. Zasada source of truth

Pamietaj:

- source of truth dla schematu = `dashboard-panel/database/v2/`
- source of truth dla danych runtime = prawdziwa baza MySQL
- backup SQL z panelu admina = tylko snapshot restore

## 13a. Ważne dla portfeli krypto po przeniesieniu

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

## 14. Najczestszy blad przy deployu

Jesli widzisz cos takiego:

- `cd: /home/.../app: No such file or directory`
- `fatal: not a git repository`
- `.../docs/namecheap-deploy.sh: No such file or directory`
- `.../docs/namecheap-import-db.sh: No such file or directory`

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
