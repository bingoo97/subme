# Krypto: weryfikacja wpłat, kursy i obciążenie

Audyt lokalny: 17.10.2026. Zakres obejmuje admina, statusy API w ustawieniach, kursy, automatyczną akceptację oraz klienta: doładowania, płatności zamówień i historię ticketów.

## Usunięte problemy

- Automatyczna akceptacja wymaga teraz potwierdzeń: BTC 2, DOGE 6, TRX 20, Ethereum 12; SOL wymaga `finalized` i rozpoznanego transferu systemowego. Progi są polityką aplikacji, nie gwarancją nieodwracalności blockchaina.
- Ethereum sprawdza udany receipt, hash, odbiorcę i rzeczywistą kwotę. Tokeny sprawdzają kontrakt i ustalone decimals. Nieudane transakcje ETH/SOL, zmiany salda SOL wynikające wyłącznie z rent, wpłaty wychodzące i daty z przyszłości nie kwalifikują się do automatycznej akceptacji.
- Wartość fiat wpłaty wynika z kursu zapisanego w jej requeście, nie z aktualnego kursu ani wartości zaimportowanej z historii.
- Hash transakcji ma jednego właściciela. Blokada skanera i blokady akceptacji ograniczają równoległe przetwarzanie. Saldo jest aktualizowane pod blokadą rekordu klienta, z idempotentnym dziennikiem i rollbackiem w razie błędu zapisu.
- Utworzenie requestu blokuje klienta i portfel oraz ponownie sprawdza otwarte requesty. Klient nie może utworzyć kilku ticketów przez równoczesne wysłanie formularza. Wywołania admina jawnie dołączają do transakcji obejmującej również wiadomość i zamówienie.
- Stary endpoint `check_payment.php` wymaga sesji klienta i POST; w nowym schemacie zwraca 410. W starym schemacie lookup faktury jest ograniczony do właściciela i escapuje kod.

## Ograniczenie kosztu

- Wspólny prywatny cache kursów i odpowiedzi API, blokady przeciw równoczesnemu pobieraniu tego samego zasobu, maksymalnie dwa połączenia do jednego dostawcy jednocześnie. Blokady nie czekają na sieć.
- Limity są współdzielone między adminem, klientem i workerem. Solana batch zużywa limit dla każdej operacji RPC, a nie tylko jednego HTTP.
- Timeout pojedynczego połączenia wynosi maksymalnie 6 s; domyślny budżet żądania to 8 s / 8 operacji. Worker skanuje maksymalnie 6 ticketów w cyklu i ma budżet 10 s / 10 operacji. Błędy mają ujemny cache i rosnącą przerwę; 429 respektuje `Retry-After`.
- Kursy mają osobne snapshoty dla walut, zwykle na 15 minut. USD używany przez narzędzia nie nadpisuje kursów EUR sklepu. Cytowanie nowej płatności odrzuca brak kursu, złą walutę, wartości niefinitywne i kurs starszy niż godzinę.
- Nowy widok klienta nie wykonuje dawnego bezwarunkowego wywołania BitPay. Legacy BitPay ma cache 5 minut, timeout i wybór waluty po kodzie zamiast pozycji w tablicy; zachowuje dotychczasowy rabat legacy 1%.
- Aktywny ticket klienta używa zapisanego kursu. Jego render nie pobiera nowej listy kursów ani blockchaina. Licznik czasu w przeglądarce nie wysyła zapytań co sekundę.
- Historia nowych ticketów używa paginacji po ID, po 50 pozycji, i indeksu `(customer_id, id)`. Wyświetla walutę requestu zamiast stałego znaku euro. Stara historia legacy pozostaje zgodna z wcześniejszym przepływem.
- Porządkowanie portfeli jest współdzielone i ograniczone do raz na minutę; maksymalnie 100 starych przypisań na przebieg. Sprawdzenie brakujących portfeli EVM danego klienta następuje najwyżej co 5 minut. Nie przepisujemy niezmienionych statusów i nie dopisujemy bez końca notatek normalizacji. Rezerwacja zawsze sprawdza bieżącą własność w DB.
- Kolejka skanera używa `blockchain_checked_at`, pomija ponowne sprawdzenie przez 120 s i przechodzi do kolejnych ticketów. Historia transakcji ma indeks `(wallet_address_id, received_at, id)`. Sprawdzenie użytych hashy jest zbiorcze.

## Statusy w ustawieniach

HTML ustawień odczytuje cache bez zewnętrznych zapytań. Otwarcie zakładki uruchamia chroniony POST w tle, bez cyklicznego pollingu. Wynik jest używany przez 5 minut. Ręczne odświeżanie ma dodatkowy odstęp 30 s; świeże udane próby są ponownie wykorzystywane. Endpoint sprawdza sesję, dostęp do ustawień i CSRF, następnie zwalnia blokadę sesji przed siecią. Interfejs ma timeout 15 s i zachowuje poprzedni wynik po błędzie.

Limit API, zajęta blokada lub wyczerpany budżet czasu daje komunikat oczekiwania, a nie informację o awarii blockchaina. Kolejne odświeżenie preferuje brakujące wyniki. Status opisuje dostępność usług API na przykładowych adresach, nie pełne działanie każdej sieci lub konkretnej wpłaty.

## Wdrożenie i granice

- Migracja: `dashboard-panel/database/migrations/021_crypto_scan_queue.sql`, idempotentna; zastosowana w lokalnej bazie aplikacji. Nie uruchamiano ręcznie cyklu maintenance ani testowych akceptacji na danych aplikacji.
- Cache domyślnie: `dashboard-panel/storage/crypto-runtime`, poza katalogiem publicznym, katalogi 0700, dane i locki 0600. Worker CLI uruchamiany jako root dziedziczy właściciela katalogu przy zapisie, żeby WWW mogło odczytywać cache. Najlepiej uruchamiać worker i WWW jako ten sam użytkownik usługi. Test potwierdza zapis przez root i odczyt/blokadę przez `www-data`.
- Przy kilku instancjach serwera te pliki i blokady muszą być rzeczywiście współdzielone lub zastąpione centralnym limiterem/cache. Obecne limity nie obejmują innych aplikacji korzystających z tego samego IP/klucza.
- Ethplorer `freekey` ma bardzo mały budżet, m.in. 100 zapytań/h. Po jego wyczerpaniu akceptacja automatyczna czeka; nie omija potwierdzeń. Dla większej liczby wpłat potrzebny jest odpowiedni klucz/plan dostawcy i dostosowanie polityki w `crypto_runtime.php`. To ograniczenie przepustowości pozostaje nawet przy szybkim serwerze.
- To audyt kodu i regresji z lokalną weryfikacją API, nie dowód braku wszystkich luk ani pomiar dużego obciążenia produkcyjnego.

## Testy

Izolowana baza z `_test_`, fikcyjne konta `.invalid`, odpowiedzi blockchaina podstawione przez prywatny cache; bez realnych wpłat i wiadomości do klientów:

- `crypto_transport_regression.php`: 35 asercji, w tym 8 procesów / jedno pobranie, 429, limity sekundowe/godzinowe/dzienne/tygodniowe i koszt batch RPC.
- `crypto_payments_regression.php`: 54 asercje: finalność, dekodowanie rzeczywistych struktur odpowiedzi, kontrakty, waluty, jednorazowa akceptacja, 8 równoległych wpłat, 8 zgłoszeń / jeden ticket, rollback, kolejka, brak zapisów przy rozgrzanym odczycie, render i paginacja klienta.
- `crypto_endpoints_regression.php`: 10 asercji: sesje, role, metody, CSRF, escaping, zwolnienie sesji, wyłączenie legacy pollera.
- `chat_security_regression.php`: 124 asercje.
- `dashboard_sales_intervals.php`: 493 asercje, w tym dołączony podstawowy test sprzedaży.
- `chat_frontend_regression.js`: 30 asercji oraz kontrola składni JS/PHP zmienionych plików.

Przykładowy aktywny ticket w izolowanej bazie: 19 zapytań SQL / około 25 ms dla kodu strony wraz z pierwszym renderem szablonu, bez wywołań zewnętrznych. To nie pomiar pełnego HTTP ani dużej bazy.

## Źródła pierwotne

- [Ethplorer API — limity freekey i struktury receiptów](https://github.com/EverexIO/Ethplorer/wiki/Ethplorer-API)
- [Solana getTransaction](https://solana.com/docs/rpc/http/gettransaction), [struktury JSON](https://solana.com/docs/rpc/json-structures)
- [CoinGecko — HTTP 429](https://support.coingecko.com/hc/en-us/articles/4538787440153-Why-am-I-getting-errors-with-a-status-code-429)
- [BlockCypher API](https://www.blockcypher.com/dev/bitcoin/)
