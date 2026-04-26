# SaaS Dashboard — wytyczne architektury, bezpieczeństwa i modelu wdrożeń

Serwis to aplikacja do zarządzania subskrypcjami które mozna kupić na określony czas od róznych dostawcow. 
Mozliwy jest sprzedaz subskrypcji w określonej walucie w panelu admina oraz darmowe subskrypcje bezplatne na krótszy czas np 6h lub 24h.

## Cel dokumentu

Ten dokument definiuje **bazową architekturę** dla Twojego SaaS-a: panelu/dashboardu do sprzedawania subskrypcji na określony czas, budowanego lokalnie na **MacBooku + Docker**, z użyciem **PHP, Smarty, SQL i Bootstrap**. Celem jest:

- bezpieczny start projektu,
- łatwe lokalne development/test/staging,
- możliwość sprzedania **tej samej aplikacji wielu klientom**,
- osobny serwer i osobna baza danych dla każdego klienta,
- przewidywalne aktualizacje przez GitHub,
- ograniczenie chaosu w plikach i „spaghetti code”.

## Aktualny stos technologiczny — rekomendacja

Na dziś sensowny i nowoczesny punkt startowy to:

- **PHP 8.4** jako główna wersja aplikacji — ma aktywne wsparcie do końca 2026 i wsparcie bezpieczeństwa do końca 2028. Dla nowego projektu to bezpieczniejszy wybór niż trzymanie się starszych gałęzi. citeturn337589search0turn337589search12turn337589search16
- **Bootstrap 5.3.8** — to aktualny release w linii 5.3, oficjalnie utrzymywany jako bieżąca wersja tej gałęzi. citeturn337589search5turn337589search9
- **Smarty v5** — oficjalne repo pokazuje bieżącą stabilną gałąź v5; projekt obsługuje współczesne wersje PHP i nadaje się do nowego kodu. citeturn337589search2turn337589search6turn337589search14
- **MariaDB** jako praktyczna baza SQL dla tego typu projektu. MariaDB utrzymuje stabilne wydania LTS i dobrze pasuje do prostych wdrożeń per-klient. citeturn337589search7turn337589search11turn337589search15

## Główna zasada architektoniczna

**Jedna codebase, wiele wdrożeń.**

To znaczy:

- masz **jeden repozytorium Git** z kodem aplikacji,
- dla każdego klienta tworzysz **osobne środowisko**:
  - osobny serwer/VPS,
  - osobną bazę danych,
  - osobny zestaw sekretów,
  - osobną domenę/subdomenę,
  - osobne backupy,
- ale **rdzeń aplikacji pozostaje wspólny**.

To jest lepsze niż mieszanie wielu klientów w jednej bazie na starcie. Multi-tenant w jednej bazie można zrobić później, ale na początku zwykle niepotrzebnie podnosi ryzyko błędów, wycieku danych i złożoność migracji.

---

# 1. Model wdrożeń

## Rekomendowany model na start

Dla pierwszych 10 klientów:

- **1 klient = 1 serwer/VPS albo 1 izolowane środowisko**
- **1 klient = 1 baza danych**
- **1 klient = 1 plik `.env` / zestaw sekretów**
- **1 klient = 1 osobny pipeline deploy lub osobny target deploy**

### Dlaczego to dobre

1. Awaria albo błąd jednego klienta nie rozwala reszty.
2. Backup/restore jest prostszy.
3. Możesz aktualizować klientów etapami.
4. Łatwiej spełnić podstawowe wymagania bezpieczeństwa i prywatności.
5. Łatwiej klientowi sprzedać „dedykowane środowisko”.

## Czego nie robić na starcie

Nie zaczynaj od:

- jednego wspólnego DB dla wszystkich klientów,
- wrzucania logiki klienta bezpośrednio do kodu typu `if client_id == 7`,
- ręcznych zmian bezpośrednio na produkcji,
- trzymania haseł i kluczy w repozytorium.

---

# 2. Proponowana struktura katalogów

[do uzupelnienia]

## Sens tej struktury

### `public/`
To **jedyny katalog publicznie dostępny z WWW**. Front controller `index.php` powinien siedzieć tutaj. Reszta kodu nie może być wystawiona bezpośrednio do internetu.

### `app/`
Tu trafia cała logika biznesowa, kontrolery, serwisy, repozytoria, middleware i walidacja.

### `resources/templates/`
Tu masz tylko warstwę widoku Smarty:
- layouty,
- partiale,
- strony,
- maile.

### `storage/`
Wszystko, co generuje runtime:
- logi,
- cache,
- pliki tymczasowe,
- kompilacje Smarty,
- sesje,
- uploady.

### `database/`
Migracje i seedery. Schemat bazy ma być odtwarzalny z repozytorium, a nie „żyjący tylko na Twoim laptopie”.

### `docs/`
To ważne. Dokumentacja architektury, zasad i procesu deploy powinna być w repo od początku.

---

# 3. Warstwy aplikacji — jak rozdzielić odpowiedzialność

## 3.1 Kontroler

Kontroler powinien:

- odebrać request,
- zawołać odpowiedni serwis,
- przekazać dane do widoku lub zwrócić JSON,
- nie zawierać ciężkiej logiki biznesowej.

### Zła praktyka
Kontroler:
- liczy subskrypcję,
- wysyła maila,
- robi SQL ręcznie,
- waliduje pola,
- generuje PDF,
- sprawdza płatność.

### Dobra praktyka
Kontroler tylko deleguje:
- `SubscriptionService`
- `BillingService`
- `UserRepository`
- `MailService`

## 3.2 Serwisy

Serwis zawiera reguły biznesowe.

Przykład:
- kiedy subskrypcja się aktywuje,
- kiedy wygasa,
- czy można ją odnowić,
- jak liczyć grace period,
- co zrobić po nieudanej płatności.

## 3.3 Repozytoria

Repozytorium odpowiada za dostęp do danych.

Nie mieszaj:
- SQL-a w template,
- SQL-a w kontrolerze,
- SQL-a w helperach od widoku.

## 3.4 Widoki Smarty

Widok ma:
- wyświetlać dane,
- używać prostych warunków,
- korzystać z partiali i layoutów.

Widok **nie ma**:
- wykonywać logiki biznesowej,
- liczyć dat subskrypcji,
- podejmować decyzji bezpieczeństwa,
- wykonywać zapytań.

---

# 4. Rekomendowany modułowy podział funkcjonalny

Na start SaaS do sprzedaży subskrypcji zwykle warto podzielić aplikację na moduły:

1. **Auth**
   - logowanie,
   - rejestracja,
   - reset hasła,
   - 2FA w przyszłości,
   - zarządzanie sesjami.

2. **Users**
   - profil użytkownika,
   - role,
   - status konta,
   - preferencje.

3. **Plans**
   - plany cenowe,
   - limity,
   - okres rozliczeniowy,
   - cechy planu.

4. **Subscriptions**
   - aktywacja,
   - odnowienie,
   - anulowanie,
   - wygasanie,
   - trial,
   - grace period.

5. **Billing**
   - płatności,
   - faktury,
   - status płatności,
   - webhooks operatora płatności.

6. **Admin Panel**
   - użytkownicy,
   - subskrypcje,
   - płatności,
   - raporty,
   - logi.

7. **Notifications**
   - maile systemowe,
   - przypomnienia o wygaśnięciu,
   - potwierdzenia płatności,
   - alerty administracyjne.

8. **Audit / Logs**
   - logi logowania,
   - zmiany krytyczne,
   - operacje administracyjne,
   - historia zmian subskrypcji.

9. **Tenant / Branding**
   - ustawienia klienta,
   - logo,
   - nazwa,
   - domena,
   - kolory,
   - feature flags.

---

# 5. Bezpieczeństwo — zasady bazowe

To nie są dodatki. To ma być standard od pierwszego dnia.

## 5.1 Zasada minimalnej ekspozycji

Publiczny jest tylko katalog `public/`. To podstawowy wzorzec bezpieczeństwa dla aplikacji PHP.

## 5.2 Sekrety poza repo

Dane typu:
- hasło DB,
- klucze API,
- SMTP,
- tokeny płatności,
- APP_KEY

muszą być poza repozytorium — np. w `.env` lub w secretach Dockera/GitHub Actions. Docker Compose wspiera sekrety montowane jako pliki w `/run/secrets/...`, a GitHub Actions wspiera secrets na poziomie repo i environment. citeturn740060search4turn740060search9turn740060search17

## 5.3 Cookies i sesje

Cookies sesyjne powinny być:
- `HttpOnly`,
- `Secure` na HTTPS,
- z sensownym `SameSite`,
- z rotacją sesji po logowaniu i krytycznych zmianach.

OWASP wskazuje `HttpOnly` jako obowiązkowy element ochrony identyfikatora sesji przed kradzieżą przez skrypt po stronie przeglądarki. citeturn740060search2

## 5.4 CSRF

Każdy formularz zmieniający stan systemu:
- musi mieć token CSRF,
- nie może polegać wyłącznie na tym, że „użytkownik jest zalogowany”.

OWASP podkreśla, że bez ochrony aplikacja nie odróżni żądania legalnego od wymuszonego przez zewnętrzną stronę. citeturn740060search6

## 5.5 Hasła

- hash tylko przez `password_hash()`,
- weryfikacja przez `password_verify()`,
- żadnego SHA1/SHA256 „na własną rękę”,
- reset hasła tylko przez jednorazowy token z wygaśnięciem.

## 5.6 SQL injection

- tylko prepared statements,
- żadnego składania SQL ze stringów użytkownika,
- whitelist dla sortowania i filtrowania.

## 5.7 XSS

- domyślnie escapuj output w template,
- nie wpuszczaj surowego HTML bez whitelist,
- osobno traktuj treści admina i zwykłego usera.

## 5.8 Rate limiting

Dla:
- logowania,
- resetu hasła,
- endpointów API,
- webhooków,
- formularzy kontaktowych.

## 5.9 Audyt działań admina

Każda krytyczna zmiana:
- planu,
- ceny,
- statusu usera,
- subskrypcji,
- ustawień klienta

powinna mieć wpis w `audit_logs`.

## 5.10 Aktualizacje zależności

Composer ma wbudowane `composer audit`, które sprawdza podatności i porzucone pakiety; nowsze wersje Composera potrafią też blokować instalację niebezpiecznych wersji pakietów w procesie rozwiązywania zależności. citeturn740060search3turn740060search11turn740060search15

---

# 6. Zasady dla SQL / bazy danych

## 6.1 Jedna prawda o schemacie: migracje

Nie wolno opierać schematu na:
- „klikanym phpMyAdmin”,
- ręcznych zmianach bez historii,
- pamięci developera.

Każda zmiana schematu:
- idzie przez migrację,
- ma numer/znacznik czasu,
- jest odtwarzalna lokalnie i produkcyjnie.

## 6.2 Minimalny zestaw tabel dla SaaS subskrypcyjnego

Przykładowo:

- `users`
- `roles`
- `user_roles`
- `plans`
- `plan_features`
- `subscriptions`
- `subscription_periods`
- `payments`
- `payment_attempts`
- `invoices`
- `invoice_items`
- `tenants`
- `tenant_settings`
- `email_logs`
- `audit_logs`
- `password_resets`
- `sessions` lub odpowiednik
- `jobs` / `scheduled_tasks`
- `webhook_events`

## 6.3 Klucze, indeksy, constraints

Standard:
- PK na każdej tabeli,
- FK tam, gdzie relacja ma znaczenie,
- indeksy pod najczęstsze query,
- unique constraints tam, gdzie dane mają być unikalne.

Przykłady:
- `users.email` unique,
- `tenants.slug` unique,
- indeks na `subscriptions.user_id`,
- indeks na `payments.subscription_id`,
- indeks na `webhook_events.external_event_id`.

## 6.4 Nie trzymaj wszystkiego w jednej tabeli

Częsty błąd: tabela `users` zawiera:
- dane profilu,
- plan,
- płatność,
- status,
- tokeny,
- branding,
- wszystko inne.

To kończy się bałaganem. Rozdzielaj byty.

---

# 7. Docker lokalnie na MacBooku — zasady

Docker Compose jest naturalnym wyborem do lokalnego developmentu, bo pozwala opisać stack aplikacji w jednym pliku YAML i uruchamiać cały zestaw usług wspólnie. Compose wspiera usługi, sieci i wolumeny, a named volumes służą do trwałego przechowywania danych. citeturn740060search0turn740060search8turn740060search12turn740060search20

## 7.1 Usługi lokalne

Na start warto mieć osobne kontenery dla:

- `nginx`
- `php-fpm`
- `mariadb`
- `mailpit`
- opcjonalnie `redis`
- opcjonalnie `phpmyadmin` tylko lokalnie, nigdy jako stały element produkcji

## 7.2 Co trzymamy w volume

- dane MariaDB,
- cache jeśli potrzebne,
- uploady jeśli mają przetrwać restart,
- czasem katalog `storage/`.

## 7.3 Czego nie robić

- nie wkładaj sekretów na sztywno do `compose.yaml`,
- nie używaj jednego kontenera do wszystkiego,
- nie wystawiaj niepotrzebnych portów,
- nie rozwijaj lokalnie na koncie root bez potrzeby.

## 7.4 Środowiska

Miej przynajmniej:
- `local`
- `test`
- `staging`
- `production`

I trzymaj różnice w:
- `.env`,
- configach środowiskowych,
- pipeline deploy.

---

# 8. GitHub i aktualizacje wielu klientów

## 8.1 Zasada

**Jeden główny produkt, różne konfiguracje klientów.**

To oznacza:
- wspólny kod w jednej gałęzi produktu,
- klientowe różnice w konfiguracji i tabelach ustawień,
- bardzo ostrożnie z klient-specyficznymi forkami.

## 8.2 Zalecany model branchy

Praktycznie:

- `main` — stabilna produkcja
- `develop` — bieżący rozwój
- `release/*` — przygotowanie wydania
- `hotfix/*` — szybkie poprawki

## 8.3 Jak aktualizować 10 klientów

Masz dwa realistyczne warianty:

### Wariant A — ten sam release dla wszystkich
Najprostszy:
- tagujesz wersję, np. `v1.4.0`
- deployujesz ją na kolejne serwery klientów
- każdy serwer ma własne sekrety i DB

### Wariant B — rollout falami
Lepszy praktycznie:
- klient 1–2 jako canary,
- potem reszta,
- rollback per klient jeśli coś pójdzie źle.

## 8.4 GitHub Actions

GitHub Actions wspiera workflowy, deployment environments i environment secrets. To dobrze pasuje do modelu „ten sam kod, różne środowiska”. Secrets mogą być trzymane na poziomie repo lub konkretnego environment. citeturn740060search1turn740060search5turn740060search13turn740060search21

### Praktyczna zasada
Dla każdego klienta możesz mieć osobny environment:
- `client-a-prod`
- `client-b-prod`
- `client-c-prod`

I dla każdego:
- osobne SSH keys,
- osobne hosty,
- osobne sekrety.

---

# 9. Tenanting i personalizacja klienta

Ponieważ chcesz sprzedawać tę samą stronę wielu osobom, trzeba od razu ustalić, co jest:

## 9.1 Wspólne dla wszystkich
- logika auth,
- logika subskrypcji,
- logika płatności,
- baza layoutów,
- admin,
- raporty,
- bezpieczeństwo.

## 9.2 Konfigurowalne per klient
- nazwa marki,
- logo,
- kolory,
- domena,
- teksty e-mail,
- waluta,
- strefa czasowa,
- dostępne plany,
- limity,
- feature flags.

## 9.3 Tego nie hardkoduj
Zamiast:
```php
if ($tenant === 'clientA') {
    $price = 99;
}
```

lepiej:
- `tenant_settings`
- `plans`
- `feature_flags`
- `branding`

czyli konfiguracja w DB albo w kontrolowanych plikach konfiguracyjnych.

---

# 10. Wymagania funkcjonalne produktu (wersja docelowa)

Poniżej jest **docelowa specyfikacja funkcjonalna** dla aplikacji do zarządzania subskrypcjami czasowymi. Ta sekcja jest nadrzędna dla dalszych prac.

## 10.1 Role użytkowników

Minimum:
- `guest`
- `user`
- `admin`

Opcjonalnie w przyszłości:
- `support`
- `billing_manager`
- `owner`

## 10.2 Główny cel produktu

System ma działać jako serwis do:
- wyboru i zakupu subskrypcji na określony czas,
- kontaktu usera z adminem przez Live Chat,
- zarządzania płatnościami i aktywacją subskrypcji przez admina.

## 10.3 Wybór subskrypcji przez usera

User musi mieć możliwość:
- przeglądania dostępnych subskrypcji,
- wyboru subskrypcji tak jak obecnie w aplikacji,
- zobaczenia ceny, czasu trwania i typu subskrypcji (test/regularna),
- przejścia do płatności.

## 10.4 Widoczność dostępu (M3U / czas do końca)

Dla usera:
- podgląd liczby dni/godzin do końca subskrypcji,
- podgląd/pobranie pliku/linku M3U **tylko po akceptacji admina**,
- domyślnie dane dostępowe są niewidoczne.

Reguła bazowa:
- `domyślnie ukryte` -> `widoczne po zatwierdzeniu przez admina`.

## 10.5 Live Chat (priorytet krytyczny)

Live Chat jest najważniejszym kanałem operacyjnym i musi działać stabilnie.

Wymagania:
- user może pisać do admina w panelu,
- admin może odpisywać do usera,
- wiadomości są trwałe (historia),
- widoczny status rozmowy (np. otwarta/zamknięta),
- możliwość wysłania przez admina gotowych danych do płatności (np. konto bankowe),
- możliwość wysłania linków i instrukcji.

## 10.6 Subskrypcje testowe (godzinowe)

Testy muszą być konfigurowalne przez admina:
- czas testu np. `6h`, `24h` (docelowo dowolny wariant godzinowy),
- możliwość globalnego włączenia/wyłączenia testów,
- możliwość ograniczeń per plan/per provider,
- po wygaśnięciu testu automatyczny status `expired`.

## 10.7 Płatności — wymagane 3 kanały

Wymagane metody płatności:
1. `PayPal`
2. `Przelew bankowy`
3. `Kryptowaluty`

Każda płatność:
- ma swój rekord i status,
- jest zatwierdzana manualnie przez admina (na obecnym etapie).

## 10.8 Przelew bankowy — model operacyjny

Admin:
- może dodać wiele kont bankowych,
- każde konto ma metadane (bank, numer konta, właściciel, waluta, status aktywne/nieaktywne),
- może przypisać konkretne konto bankowe do konkretnego usera/zamówienia.

User:
- po wybraniu płatności bankowej otrzymuje dane konta od admina przez Live Chat,
- wykonuje przelew,
- czeka na ręczne zatwierdzenie.

## 10.9 Kryptowaluty — portfel per user

Admin:
- dodaje adresy portfeli krypto,
- wybiera kryptowalutę (np. BTC/ETH/USDT itd.),
- przypisuje portfel do konkretnego usera/zamówienia.

System:
- generuje QR Code dla adresu i kwoty,
- pokazuje userowi dane do opłacenia,
- status płatności pozostaje `oczekuje` do manualnego zatwierdzenia przez admina.

## 10.10 Cykl życia subskrypcji

Rekomendowane statusy:
- `pending`
- `trialing`
- `active`
- `awaiting_admin_approval`
- `past_due`
- `grace`
- `cancelled`
- `expired`

Uwagi:
- nie używać nieopisanych statusów liczbowych,
- statusy powinny być jawne i spójne między UI, backendem i bazą.

## 10.11 Logika aktywacji

Aktywacja subskrypcji następuje po:
- poprawnym zamówieniu,
- spełnieniu warunku płatności,
- manualnym zatwierdzeniu przez admina.

Dopiero po aktywacji user dostaje:
- aktywną subskrypcję,
- datę końca,
- dostęp do danych/subskrypcji zgodnie z polityką widoczności.

---

# 11. Zasady kodowania

## 11.1 Standardy

- PSR-12 dla stylu kodu
- autoload przez Composer
- ścisły podział namespace
- jedna klasa = jedna odpowiedzialność

## 11.2 Nazewnictwo

- kontrolery: `SomethingController`
- serwisy: `SomethingService`
- repozytoria: `SomethingRepository`
- middleware: `SomethingMiddleware`

Unikaj nazw typu:
- `functions.php`
- `new2.php`
- `final_final.php`
- `checker.php` jako worek na wszystko

## 11.3 Konfiguracja

Konfiguracja:
- w `app/Config/`
- lub czytelnych plikach config
- nie rozsiana po 20 plikach.

## 11.4 Helpery

Helpery tylko do małych, uniwersalnych rzeczy. Nie rób z `helpers.php` drugiego frameworka.

---

# 12. Testy i jakość

Na początku nie musisz mieć 100% coverage, ale musisz testować rzeczy najbardziej ryzykowne:

## 12.1 Unit tests
- logika subskrypcji,
- naliczanie okresów,
- statusy,
- walidacja planów.

## 12.2 Integration tests
- repozytoria,
- baza danych,
- webhooki,
- autoryzacja.

## 12.3 Feature tests
- rejestracja,
- logowanie,
- zakup planu,
- odnowienie,
- anulowanie.

## 12.4 Kontrola jakości w CI
Minimum:
- `composer validate`
- `composer audit`
- linter / code style
- testy

Composer ma oficjalne wsparcie dla `audit`, więc ten krok warto traktować jako obowiązkowy w pipeline. citeturn740060search3turn740060search11turn740060search23

---

# 13. Dokumenty, które powinny istnieć w repo

Minimum:

- `README.md`
- `docs/ARCHITECTURE.md`
- `docs/SECURITY.md`
- `docs/DEPLOYMENT.md`
- `docs/DATABASE.md`
- `docs/PRODUCT-RULES.md`
- `docs/TENANT-OVERRIDES.md`

## Co powinien zawierać `PRODUCT-RULES.md`
- jakie są role,
- jak działa trial,
- kiedy subskrypcja wygasa,
- co dzieje się po nieudanej płatności,
- co widzi admin,
- co wolno supportowi,
- jakie są reguły upgrade/downgrade.

To ma być „źródło prawdy” produktu.

---

# 14. Minimalny zestaw zasad operacyjnych

1. **Nigdy nie edytujesz kodu bezpośrednio na produkcji.**
2. **Każda zmiana bazy ma migrację.**
3. **Każdy klient ma osobne sekrety.**
4. **Każdy klient ma osobny backup bazy.**
5. **Nowe funkcje najpierw na staging.**
6. **Logi bezpieczeństwa są przechowywane osobno od zwykłych logów.**
7. **Nie trzymasz danych płatniczych, jeśli operator płatności może to robić za Ciebie.**
8. **Każdy endpoint POST/PUT/DELETE ma ochronę CSRF albo inną adekwatną kontrolę dla API.**
9. **Każdy admin ma audit trail.**
10. **Każda wersja wdrożeniowa ma tag release.**

---

# 15. Praktyczna rekomendacja startowa dla Ciebie

Na Twoim miejscu startowałbym tak:

## Etap 1 — fundament
- PHP 8.4
- Smarty v5
- Bootstrap 5.3.8
- MariaDB
- Docker Compose
- Nginx + PHP-FPM
- Composer
- prosty router i własna architektura warstwowa

## Etap 2 — core produktu
- auth
- users
- plans
- subscriptions
- billing
- admin
- audit logs

## Etap 3 — gotowość pod sprzedaż dla wielu klientów
- `tenants`
- `tenant_settings`
- branding
- feature flags
- osobne `.env` i deploy per klient

## Etap 4 — automatyzacja
- GitHub Actions
- testy
- migracje w pipeline
- rollout per klient
- backup/restore procedure

---

# 16. Decyzje architektoniczne, które warto zatwierdzić już teraz

## Decyzja 1
Czy robisz:
- **monolit modułowy** — rekomendowane,
czy
- mikroserwisy — na tym etapie nie.

**Wniosek:** rób monolit modułowy.

## Decyzja 2
Czy wszyscy klienci siedzą w jednej bazie?
**Wniosek:** nie. Osobna baza per klient.

## Decyzja 3
Czy personalizacja klienta ma być w kodzie?
**Wniosek:** nie. W konfiguracji i tabelach settings.

## Decyzja 4
Czy lokalny Docker ma odwzorowywać produkcję?
**Wniosek:** tak, możliwie blisko, ale bez przesady.

## Decyzja 5
Czy Smarty ma mieć dużo logiki?
**Wniosek:** nie. Smarty ma wyświetlać, nie zarządzać biznesem.

---

# 17. Podsumowanie strategiczne

Najrozsądniejszy start dla takiego projektu to:

- **jeden wspólny kod aplikacji,**
- **modułowy monolit,**
- **osobne wdrożenie i DB per klient,**
- **konfiguracja klienta zamiast forków,**
- **Docker lokalnie,**
- **GitHub jako źródło prawdy,**
- **migracje jako jedyne źródło prawdy o bazie,**
- **bezpieczeństwo wdrożone od początku, nie „kiedyś”.**

To nie jest jeszcze architektura pod tysiące tenantów na jednej platformie, ale dla pierwszych klientów jest dużo bezpieczniejsza, prostsza i realnie łatwiejsza do utrzymania.

---

# 18. Co dalej (na podstawie tej specyfikacji)

Następny krok po dopracowaniu treści dokumentu:

1. Rozpisanie modelu danych pod:
   - subskrypcje regularne i testowe (godzinowe),
   - Live Chat,
   - płatności PayPal/Bank/Krypto,
   - portfele krypto per user,
   - konta bankowe i przypisania do usera.
2. Ustalenie statusów i przejść statusów (state machine).
3. Rozpisanie makiet UI:
   - panel usera,
   - panel admina,
   - moduł Live Chat.
4. Rozpisanie endpointów i akcji admina (manualne zatwierdzanie).
5. Dopiero potem implementacja kodu modułami.

