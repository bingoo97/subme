# Import klientów w Users

Zmiana lokalna, bez migracji bazy. Import dostępny wyłącznie aktywnemu administratorowi z uprawnieniem Users, przez POST z CSRF.

## Obsługa

1. Wklej dane albo wybierz plik. CSV, TSV, TXT, HTML tabeli lub eksport SQL INSERT z nazwami kolumn; do 10 MB i 10 000 unikalnych kont.
2. Sprawdź podgląd. E-mail, hasło i status są rozpoznawane automatycznie. W razie potrzeby rozwiń mapowanie kolumn i wybierz interpretację 0/1. Nieznany status oznacza konto nieaktywne; brak kolumny używa statusu wybranego w formularzu.
3. Wybierz język, typ klienta i widocznych providerów. Uruchom import. Można go wstrzymać i wznowić po odświeżeniu strony w tej samej sesji przeglądarki.

Listy e-maili obsługują nowe linie, przecinki, średniki i spacje. Wiersze CSV/TSV z hasłami muszą używać normalnego separatora/quotingu; nagłówek pomaga rozpoznać kolumny. Eksport SQL jest odczytywany jako dane, nigdy wykonywany. Funkcje SQL, INSERT bez listy kolumn i mieszanie różnych tabel/układów kolumn są odrzucane. Import nie tworzy zamówień, sald, ról administratora ani portfeli.

## Hasła i tożsamość

- Handle powstaje z części e-maila przed @. Kolizje dostają krótką końcówkę, bez przeszukiwania tysięcy kolejnych numerów.
- Zwykłe hasło jest hashowane tak jak przy rejestracji. Zgodny bcrypt/Argon2 można zachować; koszt hasha jest ograniczony.
- Puste, nieobsługiwane lub niejednoznaczne hashe wymagają nowego losowego hasła. Nie można odzyskać oryginalnego hasła z niezgodnego hasha.
- Włączona wysyłka dodaje do istniejącej kolejki e-mail tylko **nowo wygenerowane** hasła. Import nie czeka na SMTP. Wymagane skonfigurowane SMTP i aktywny szablon password-reset; dostarczenie realizuje istniejący worker kolejki. Wyłączenie wysyłki pozostawia klientowi standardowe odzyskiwanie hasła.
- Istniejące e-maile są pomijane bez nadpisywania hasła, statusu lub salda. Duplikaty w źródle są liczone w podglądzie.
- IP/kraj z pliku nie są importowane. Prawdziwe IP zapisuje logowanie klienta. Kraj wymaga GeoIP udostępnionego przez serwer (GEOIP_COUNTRY_CODE / MM_COUNTRY_CODE), lokalnego rozszerzenia geoip albo MaxMind Db Reader i APP_GEOIP_DATABASE. Bez GeoIP kraj pozostaje nieznany; nie zgadujemy go z języka.

## Stabilność i przechowywanie

Podgląd nie zapisuje klientów ani e-maili. Istniejące adresy sprawdzamy w grupach po 500. Zapis odbywa się sekwencyjnie, maksymalnie 25 kont lub około 5 sekund na żądanie (trwający hash może przekroczyć ten budżet). Konto, uprawnienia, audyt i kolejka wiadomości są zapisywane w jednej transakcji. Błąd kolejki zatrzymuje import przed przesunięciem kursora. Ponowienie pomija już utworzone konta.

Źródło i stan są szyfrowane kluczem aplikacji w prywatnym runtime/customer-imports (folder 0700, pliki 0600), wygasają po 2 godzinach, a zakończenie usuwa dane źródłowe. Sprzątanie wygasłych plików odbywa się przy kolejnym użyciu importu. sessionStorage zawiera tylko losowy identyfikator zadania, powiązany z administratorem; żadnych haseł. Blokada pliku zapobiega równoległemu wykonaniu tego samego zadania. Po niepewnej odpowiedzi żądania nie są automatycznie powtarzane — wznowienie najpierw sprawdza zapisany postęp.

## Weryfikacja

- `python3 dashboard-panel/tests/run_customer_import.py`: jednorazowa pusta baza, 263 asercje, 5000 syntetycznych kont, bez realnej wysyłki.
- `node dashboard-panel/tests/customer_import_frontend_regression.js`: pauza podczas trwającej partii, wznowienie, utrata odpowiedzi i brak automatycznych powtórzeń.
- Rzeczywisty formularz HTTP oraz upload multipart SQL, podgląd i zapis; widoki 390 i 1440 px. E-maile wyłącznie do kolejki w bazie testowej.
- 67 renderów podstron bez ostrzeżeń PHP; osobno sekcja uprawnień i tożsamości administratorów. Pełny starszy test admin_identity_security_regression zatrzymuje się na nieaktualnym oczekiwaniu kategorii krypto: wygasły ticket z wykrytą wpłatą pozostaje obecnie w Nowe do weryfikacji.
