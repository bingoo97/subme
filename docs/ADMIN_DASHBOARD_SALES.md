# Dashboard: sprzedaż, płatności i zamówienia

Dashboard pokazuje **sprzedaże**, a nie liczbę wpłat na portfel lub liczbę kliknięć „zatwierdź”.

- Jeden opłacony zakup = jedna sprzedaż.
- Każde opłacone przedłużenie = osobna sprzedaż, także w stanie `paid_pending_activation`.
- Późniejsza aktywacja tego samego przedłużenia nie dodaje kolejnej sprzedaży.
- Doładowanie salda samo w sobie nie jest sprzedażą; zakup za to saldo jest sprzedażą.
- Zamówienia nieopłacone, anulowane, zwrócone, bezpłatne oraz z przyszłą datą płatności nie podnoszą wyniku.
- Przedłużenia anulowane i oczekujące na płatność nie są sprzedażą. Zduplikowane rekordy przedłużenia z identycznym typem i ID płatności są liczone raz.
- Ręczne przedłużenie z dodatnią ceną i statusem `applied` jest traktowane jako opłacona sprzedaż, również przy rozliczeniu poza saldem. Sam brak obciążenia salda nie jest dowodem braku płatności.

## Data i kwota

Zakup używa pierwotnych danych `orders.initial_sale_*` (utrwalanych przed pierwszym przedłużeniem). Dla zamówień bez przedłużeń używane są `paid_at` i dane zamówienia; przy braku starej daty płatności pozostaje zgodność z wcześniejszym fallbackiem do `created_at`.

Przedłużenie używa `order_renewals.paid_at`. Dla starszych rekordów fallback to data uznania powiązanej wpłaty w `customer_balance_runtime_events`, następnie `applied_at` i `requested_at`. Status `applied` jest historycznym potwierdzeniem płatnego przedłużenia. Aktualna cena i pakiet subskrypcji nie mogą zmieniać wartości pierwotnego zakupu.

Wszystkie karty oraz serie dzienne, miesięczne, roczne, podsumowanie produktów i dostawców korzystają z tego samego źródła `admin_dashboard_sales_source_sql`. Dni i okresy odpowiadają strefie aplikacji (`APP_TIMEZONE`, domyślnie `Europe/Warsaw`); przedziały mają domknięty początek i otwarty koniec. Daty w istniejących polach DATETIME są interpretowane zgodnie z dotychczasową strefą aplikacji.

Waluty nie są dodawane do siebie ani przeliczane dzisiejszym kursem. Kwoty są prezentowane osobno, np. `30.00 EUR + 7.00 USD`. Słupki przychodu i wskazanie najlepszego miesiąca/roku odnoszą się do waluty domyślnej, opisanej w panelu. Liczba sprzedaży obejmuje wszystkie waluty. Średnie miesięczne uwzględniają cały zakres 12 miesięcy, także miesiące i dni bez sprzedaży.

## Migracja i starsze dane

Schemat: `dashboard-panel/database/v2/001_core_schema.sql`. Migracja przyrostowa: `dashboard-panel/database/migrations/019_preserve_original_sales.sql`. Runtime ma zgodny helper dla istniejących instalacji.

Odzyskiwanie danych już nadpisanych przez dawny kod:

```sh
# Najpierw kopia orders i order_renewals; potem podgląd:
php dashboard-panel/scripts/recover_original_sales.php
# Po sprawdzeniu wyniku:
php dashboard-panel/scripts/recover_original_sales.php --apply
```

Skrypt utrwala dane jedynie dla wcześniej przedłużanych zamówień z zachowanym pierwotnym obciążeniem `order_activation`. Nie używa częściowych obciążeń oznaczonych jako niedopłata. Starszą datę pełnej, powiązanej wpłaty może odzyskać z historii uznań salda. Jest idempotentny: nie nadpisuje istniejących snapshotów.

Historia salda nie przechowuje waluty ani produktu. Skrypt zachowuje walutę zamówienia; przed użyciem w środowisku z historycznymi zmianami waluty trzeba zweryfikować te rekordy z archiwalnymi płatnościami. Jeśli odzyskana kwota różni się od obecnej ceny zamówienia, pierwotny pakiet jest oznaczony jako nieznany (`initial_sale_product_id = 0`). Taka sprzedaż pozostaje w oddzielnym wierszu historycznym w podsumowaniu, bez przypisywania do obecnego pakietu. Wykres dostawców obejmuje tylko sprzedaż z identyfikowalnym produktem. Braku informacji w usuniętych rekordach nie można odtworzyć z samej obecnej ceny katalogowej.

## Test regresji

`dashboard-panel/tests/dashboard_sales_regression.php` wymaga osobnej bazy zawierającej `_test_` w nazwie, z wgranym schematem canonical. Skrypt usuwa dane testowych tabel w tej bazie i nie może być uruchamiany na bazie aplikacji.

Obejmuje zakup, zatwierdzenie i aktywację przedłużenia, powtórzenia, duplikaty powiązanej wpłaty, doładowania salda, wykluczone statusy, przyszłe daty, granicę dnia, waluty, nieaktywne i nieznane produkty, zgodność kart/wykresów/podsumowania oraz geometrię SVG.

## Rozszerzony audyt interwałów

`dashboard-panel/tests/dashboard_sales_intervals.php` uruchamia także bazowy zestaw regresji. W izolowanej bazie wykonuje 493 asercje, w tym:

- granice dnia, miesiąca, roku i tygodnia ISO, 29 lutego, przełom grudnia/stycznia oraz dni zmiany czasu;
- odrębne daty utworzenia, płatności i aktywacji przedłużenia;
- zgodność miesięcy i lat z filtrem roku i sumami produktów;
- 300 produktów (sumy nie są już ograniczane limitem wyświetlanych pozycji), dwie waluty i kwoty groszowe;
- wcześniejszy zduplikowany wpis z przyszłą datą, ceną zero lub anulowanym/zwróconym zamówieniem;
- różne kanały z tym samym liczbowym ID płatności i niezgodnego właściciela przedłużenia;
- rzeczywistą aktywację i jej powtórzenie oraz wycofanie zmiany pakietu po wymuszonym błędzie zapisu przedłużenia;
- równoległy zapis sprzedaży na drugim połączeniu i niezmienność bieżącego odczytu raportu.

Dashboard używa jednej chwili odniesienia i jednego odczytu transakcyjnego `REPEATABLE READ` dla kart, wykresów i podsumowania. Następne odświeżenie pokazuje wpłaty zatwierdzone podczas poprzedniego odczytu. Kolumny zgodności i ustawienia waluty są przygotowywane przed rozpoczęciem odczytu.

Aktywacja aktualizuje pakiet zamówienia i stan przedłużenia w jednej transakcji, z blokadą rekordu. Błąd zapisu nie pozostawia zmienionego pakietu ze starym stanem przedłużenia. Wcześniejsze zarezerwowanie salda jest odrębną, idempotentną operacją; ponowienie aktywacji nie pobiera tej samej rezerwacji drugi raz. Przedłużenia anulowane i zamówienia zwrócone nie mogą zostać aktywowane tą ścieżką. Pominięcie obciążenia wymaga opłaconego stanu lub istniejącego wpisu obciążenia salda.

Ograniczenie danych historycznych: pola DATETIME przechowują czas lokalny bez offsetu UTC. Dwa wpisy z tej samej powtórzonej godziny jesiennej są odrębnymi sprzedażami i pełne sumy dnia/miesiąca uwzględniają oba. Nie da się jednak jednoznacznie odtworzyć ich kolejności ani dokładnego chwilowego odcięcia raportu w trakcie tej powtórzonej godziny bez dodatkowej historii UTC. Testy zmiany czasu potwierdzają pełne przedziały kalendarzowe, a nie rekonstrukcję nieistniejących offsetów starych wpisów.

## Odczyt i prezentacja

Pełne zakresy kalendarzowe korzystają ze wspólnego dziennego agregatu SQL, ponownie używanego wyłącznie w obrębie jednego odczytu raportu. Cache jest czyszczony po zakończeniu raportu, więc kolejne odświeżenie uwzględnia nowe płatności. Pozostałe podstrony nie uruchamiają obliczeń sprzedaży dashboardu. Indeks powiązanych płatności przedłużeń dodaje migracja `020_dashboard_sales_query_index.sql`.

Renderowanie HTML korzysta z zapisanych kursów walut; pobieranie kursów z zewnętrznych API odbywa się asynchronicznie po otwarciu kalkulatora, bez blokowania sesji administratora. Metadane tabel i kolumn są grupowane w cache bieżącego żądania.

Wykres zachowuje dotychczasowe trzy zakładki. Szczegóły dnia zawierają datę, przychód według waluty, liczbę sprzedaży oraz podział na zakupy i przedłużenia. Dostępne są dotyk i nawigacja klawiaturą. Kafelki okresów mają dwie kolumny na małych ekranach. Wspólny `admin-page-hero` używa mniejszej typografii na wszystkich podstronach.
