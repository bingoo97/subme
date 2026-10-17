# Live Chat — lokalny audyt funkcji i bezpieczeństwa

Zakres: endpoint administratora, wspólne renderowanie wiadomości i podglądów linków, dostęp do audio, tworzenie requestu krypto i mobilny composer. Układ i stylistyka czatu zostały zachowane.

## Zmienione zachowanie

- Operacje modyfikujące wymagają POST oraz poprawnego CSRF. Konto administratora musi mieć dostęp do Live Chatu.
- Odczyt nagrań, historia, quick replies i usuwanie prywatnych rozmów sprawdzają uprawnienia do rozmowy. Oczekujące zaproszenie nie daje dostępu do historii. Wygasłe nagrania nie są serwowane.
- Niebezpieczne schematy URL w kartach płatności są odrzucane. Awaryjne renderowanie bez DOM nie zachowuje potencjalnie aktywnego HTML.
- Podgląd linku sprawdza każdy redirect przed połączeniem, przypina sprawdzony publiczny adres IP i ogranicza pobranie do 256 KiB. Nie korzysta z niesprawdzonego fallbacku HTTP. Wolne podglądy i audio nie trzymają blokady sesji administratora.
- Wysłanie wiadomości nie zgłasza sukcesu po nieudanym zapisie. Błąd zachowuje draft, szybkie kliknięcia nie dublują wysyłki, a późna odpowiedź nie przełącza rozmowy ani nie usuwa następnego draftu. Enter podczas kompozycji IME nie wysyła wiadomości.
- Obrazy mają limit 8 MiB, 8192 px na bok i 16 mln pikseli. Brak pliku oraz wyłączona funkcja audio są obsługiwane jako błędy. Usunięcie rozmowy usuwa również reakcje.

## Request krypto

Zapis requestu, nowego przypisania portfela, karty wiadomości i opcjonalnego zamówienia jest wspólną transakcją. Helper przypisania portfela wywołany z tego procesu nie wykonuje zagnieżdżonego COMMIT. Klient i portfel są blokowani na czas zapisu. Ponowne sprawdzenie oczekującego requestu następuje po uzyskaniu blokady.

Podgląd i zapis korzystają z tej samej waluty i kursu. Nieprawidłowe kwoty nie są po cichu obcinane do 200. Produkty z obcej waluty nie są przedstawiane jako produkty w walucie domyślnej. Cena zmieniona w formularzu staje się także kwotą tworzonego zamówienia. Sieć i opcjonalny Memo/Tag są przekazywane do podglądu i karty płatności. Spóźniony podgląd nie nadpisuje nowszego wyboru, a przycisk tworzenia chroni przed równoczesnymi wysyłkami.

## Weryfikacja

Na osobnej bazie z canonical schema `database/v2/001_core_schema.sql` i seedem `002_seed_defaults.sql`:

```sh
DB_NAME=reseller_chat_test_... php dashboard-panel/tests/chat_security_regression.php
node dashboard-panel/tests/chat_frontend_regression.js
```

Zestaw PHP celowo usuwa dane fixture'ów; odmawia pracy bez `_test_` w nazwie bazy. Tworzy wyłącznie sztuczne konta `example.invalid`. Testuje rzeczywisty endpoint w osobnych procesach, w tym równoczesne requesty, rywalizację o ostatni portfel i wymuszone błędy zapisu. Nie wysyła testowych kart do rzeczywistych klientów.

Wyniki: 124 asercje endpointu/bezpieczeństwa/płatności, 30 asercji interfejsu, zapamiętywania widoczności i klawiatury; dodatkowo 493 asercje regresji sprzedaży. Zweryfikowano też zwykły publiczny podgląd linku oraz bezpieczny fallback bez rozszerzenia DOM.

Mobilny panel używa rzeczywistej wysokości i przesunięcia `visualViewport`, bez minimum 320 px zasłaniającego pole pod klawiaturą. Input ma 16 px, a historia może się kurczyć i przewijać niezależnie od composera. Sprawdzono w przeglądarce szerokości 320/390 px i wysokości 260/300/844 px. Zmniejszony viewport symuluje przestrzeń po klawiaturze; test fizycznej klawiatury iOS/Android nie był dostępny w tym środowisku. Podgląd krypto sprawdzono na lokalnym panelu, a tworzenie kart i zamówień w izolowanej bazie.

Audyt dotyczy wymienionych ścieżek i przypadków testowych; nie jest dowodem braku wszystkich możliwych błędów w całym komunikatorze.


## Boczny czat administratora

Od szerokości 1280 px czat zajmuje osobną prawą kolumnę pod navbarem: płynnie od 300 do 460 px (`clamp(300px, 22vw, 460px)`), np. 352 px przy 1600 px i 422 px przy 1920 px. Lewe menu ma 240 px. Treść panelu zwalnia miejsce dla czatu; kliknięcia w obszar roboczy go nie zamykają. Poniżej 1280 px zachowano pływające okno, a do 768 px widok pełnoekranowy.

Bez zapisanej preferencji czat otwiera się domyślnie na desktopie. Jawne wyłączenie przełącznikiem jest zapamiętane pomiędzy podstronami i adresami `/admin/` oraz `/admin/index.php`; wcześniejsze ustawienie jest migrowane. Renderowanie odpowiedzi z pollingu i wysyłania nie otwiera okna ponownie. Regresja JS obejmuje również domyślne ustawienia, migrację, zmianę adresu, spóźnione odpowiedzi po zamknięciu oraz kliknięcia poza kolumną.

Inbox ma wyszukiwarkę i ikonki w jednej linii, bez osobnego tytułu. Nagłówek rozmowy mieści przycisk powrotu, rozmówcę oraz pojedynczy przycisk opcji. Dostępne akcje rozwijają się z ikonami i opisami; menu zamyka kliknięcie poza nim, wybór akcji lub Escape. Zachowano istniejące handlery i warunki dostępności akcji. Pusta rozmowa ma wyśrodkowaną ikonę i komunikat, bez zmieniania stanów ładowania/błędu.

Formularze czatu (m.in. grupa, szybkie dodawanie usera i płatności) otwierają się w oknach do 560 px nad obszarem roboczym, obok lewego menu. Mają spójną typografię, mniejsze kontrolki na desktopie oraz przewijanie na małych ekranach. Mobilne pola mają 16 px; wysokość nakładki korzysta z visualViewport. Zweryfikowano kolumnę 300 px, ekran 390 px oraz 320×260 px (symulacja miejsca po klawiaturze), bez tworzenia rzeczywistych użytkowników, grup ani płatności.

Tekst w wierszach inboxa jest wyśrodkowany względem avatara. Przycisk usuwania nie dodaje pustego wiersza do bloku tożsamości, a pusty podgląd wiadomości nie zajmuje miejsca. Sprawdzono wyrównanie środka avatar/tekst na desktopie i przy szerokości 320 px.

Dymki administratora mają mniejsze wypełnienie, odstępy i zaokrąglenia; tekst 12 px na desktopie i 12,5 px na telefonie ma normalną wagę. Długie identyfikatory i adresy zawijają się w kolumnie. Audio i załączniki nie wymuszają szerszego dymka; podgląd linku ma ciemny tekst na białym tle. Karty płatności wykorzystują szerokość kolumny. Zachowano kontrolki wiadomości i osobne style stanów systemowych. Wygląd sprawdzono na tymczasowym statycznym podglądzie z przykładowymi danymi (tekst, cytat, reakcja, długi identyfikator, link, audio, obraz i karta płatności), bez zapisów do rozmów. Podgląd usunięto po testach. Sprawdzone szerokości: 320/390/1280/1600/1920/2560 px, bez poziomego przepełnienia historii.
