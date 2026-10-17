# Audyt modułu Rachunki — 17.10.2026

Zakres: kontrola dostępu, kwoty i daty, wystawianie, edycja szkiców, zmiany statusu, powiązania z klientem i płatnością, kartoteka nabywców, wysyłka, PDF i obciążenie przy otwieraniu zakładek.

## Naprawione nieścisłości i błędy

1. **Ominięcie uprawnień przy odczycie.** Obsługa PDF i wyszukiwania rachunków znajdowała się przed ogólną kontrolą uprawnień do trasy. Oba odczyty wymagają teraz dostępu do Rachunków przed zwróceniem danych. Odpowiedzi zawierające dane rachunku mają `no-store`. Wyszukiwanie zwalnia blokadę sesji przed zapytaniami.
2. **Równoczesne działania na dokumencie.** Wystawienie tego samego szkicu, edycja i oznaczanie jako opłacony/anulowany korzystają ze wspólnej blokady dokumentu. Status szkicu jest ponownie sprawdzany pod blokadą rekordu SQL. Wydany dokument nie może zostać cofnięty do szkicu przez spóźniony zapis.
3. **Podwójne wysłanie nowego formularza.** Dokument niezależny ma losowy identyfikator formularza, przechowywany w istniejącym unikalnym kluczu źródła. Ponowienie zwraca istniejący dokument zamiast przydzielać kolejny numer. Anulowanie dokumentu niezależnego zachowuje ten identyfikator; ponowienie starego formularza nie wystawia nowego dokumentu. Powiązania źródeł płatności nadal można zwolnić przy anulowaniu.
4. **Niepełny rollback.** Opcjonalny zapis nabywcy i nadanie numeru są częścią tej samej transakcji co rachunek i jego pozycje. Błąd pozycji wycofuje całość. Sprawdzany jest również wynik usuwania starych pozycji i COMMIT.
5. **Niespójne dane wystawcy.** Wystawca jest ponownie blokowany i sprawdzany przed zapisem. Zmiana jego istotnych danych w trakcie przetwarzania przerywa zapis. Późniejsza zmiana logo profilu nie uzupełnia ani nie zmienia snapshotu już wystawionego rachunku.
6. **Nieprawidłowe liczby.** Błędny zapis liczby nie zmienia się już automatycznie w zero. Nie są akceptowane wartości niefinitywne, przekroczenie zakresów kolumn ani ponad 200 pozycji. Walidacja obejmuje ceny netto/brutto, ilość, VAT, cenę nabycia w marży i sumę dokumentu. Poprawiono ścisłą walidację dat, w tym znaki NUL i nieistniejące daty.
7. **Zmiana domyślnej stawki.** Wyczyszczenie poprzedniej stawki domyślnej i zapis nowej są atomowe i serializowane dla kraju. Błędne ID lub nieudany INSERT nie pozostawiają kraju bez dotychczasowej stawki domyślnej.
8. **Starsze źródła.** Wskazana płatność lub zamówienie są odczytywane bezpośrednio po ID. Dawny limit 500 najnowszych rekordów nie powoduje już fałszywego komunikatu o niedostępnym źródle. Edytowany szkic zachowuje swoje źródło także wtedy, gdy jest ono starsze niż 300 widocznych opcji.
9. **Kartoteka nabywcy.** Zapis z formularza dokumentu zachowuje nazwę firmy i telefon. Nie pozwala powiązać niezależnego rachunku z innym kontem niż konto wskazanego klienta rozliczeniowego. Dla źródła płatności zapis kartoteki używa rzeczywistego klienta źródłowego.
10. **Wysyłka.** Wysyłka bezpośrednia i akcje dokumentu korzystają z tej samej nieblokującej blokady. Równoczesne kliknięcia nie omijają kontroli niedawnej wysyłki; anulowanie nie przeplata się z trwającą wysyłką. Nie utrzymujemy transakcji SQL podczas SMTP. Brak potwierdzenia odbiorcy zatrzymuje operację przed utworzeniem dostawy. W testach nie wysyłano wiadomości.
11. **Logo/PDF.** Odczyt logo sprawdza rzeczywistą ścieżkę w katalogu uploadów rachunków, format, rozmiar i wymiary rastra. Zewnętrzne zasoby Dompdf pozostają wyłączone. Wielostronicowy PDF ma numer dokumentu i numerację stron oraz nie dzieli pozycji pomiędzy strony.

## Workflow

Zachowano podział Utwórz / Statystyki / Rachunki / Klienci / Ustawienia. Formularz nadal pozwala zapisać szkic przed ukończeniem pełnej kontroli wymaganej do wystawienia.

Lista kontroli pokazuje tylko warunki właściwe dla dokumentu: np. nie potwierdza sprawdzenia VAT ID dla zwykłego konsumenta i nie pokazuje kontroli marży dla zwykłej usługi. Uwzględnia daty i zakresy liczb przed włączeniem wystawiania. Widoczne etykiety powiązano z kontrolkami formularza.

Udane operacje POST kończą się przekierowaniem do GET z komunikatem. Odświeżenie strony po zapisie klienta lub zmianie statusu nie powtarza poprzedniej operacji. Błędy wystawiania zachowują wpisane ceny brutto i tożsamość formularza. Lokalny autosave jest rozdzielony według administratora i nie przywraca szkiców starszych niż 7 dni.

Wszystkie pięć zakładek ma spójne kompaktowe karty, typografię, kontrolki i nawigację. W formularzu wystawiania wybór istniejącego klienta i edycja danych nabywcy są wzajemnie zwijanymi sekcjami. Po wybraniu klienta otwierają się dane do kontroli. Referencja księgowa znajduje się w Ustawieniach zaawansowanych, a uwagi w osobnej sekcji; wymagane kontrole KSeF/VAT pozostają poza nimi. W kartotece formularz dodawania jest zwinięty, a edycja istniejącego klienta otwiera go automatycznie. Automatyzacja, stawki VAT i katalog w ustawieniach są dostępne po rozwinięciu. Walidacja otwiera sekcję zawierającą błędne pole przed ustawieniem fokusu.

## Obciążenie

- Inicjalizacja schematu rachunków została usunięta z wejścia na każdą stronę, w tym ekran logowania. W panelu jest uruchamiana dla uprawnionych użytkowników zakładek Rachunki/Ustawienia; ścieżka automatyzacji zachowuje swoją inicjalizację.
- Każda zakładka ładuje tylko potrzebne zestawy. Utwórz nie pobiera całej listy dokumentów ani statystyk, a lista dokumentów nie pobiera źródeł, kartotek i statystyk. Usunięto niewykorzystywane zapytanie historii automatyzacji.
- Wyszukiwanie ma limit długości i wyników; pojedyncze numery ID są obsługiwane. Lista dokumentów zachowuje dotychczasowy limit 100, a źródła limit 300, z wyjątkiem dołączanego aktualnego źródła edytowanego szkicu.
- Zajęta blokada operacji zwraca krótki komunikat o przetwarzaniu zamiast oczekiwać na zakończenie SMTP lub innego działania.

## Weryfikacja

`dashboard-panel/tests/billing_regression.php`: **66 asercji** na izolowanej bazie `_test_`. Obejmuje 8 procesów równocześnie wystawiających ten sam dokument, równoczesne wystawienie szkicu i zmiany statusów, rollback rachunku/nabywcy/numeru, błędy stawek, kwoty i daty, starsze źródła, kontrolę dostępu do faktycznych endpointów, brak CSRF, PRG i ograniczenie ładowanych danych.

`dashboard-panel/tests/billing_frontend_regression.js`: **6 asercji** — przełączanie wyszukiwania/danych nabywcy, automatyczny podgląd wybranego klienta i otwieranie zagnieżdżonych sekcji przy błędzie walidacji. Sprawdzono pięć zakładek lokalnie oraz układ przy szerokości 390 px.

Wygenerowano i obejrzano obie strony testowego PDF z 32 pozycjami i polskimi znakami. Zweryfikowano obecność ostatniej pozycji, podsumowania i numeracji stron. Dane były syntetyczne, adresy `.invalid`; dokumentów nie wysyłano.

Regresje uzupełniają istniejące testy czatu/płatności, sprzedaży i frontendowych interakcji. Testy nie wystawiają dokumentów na bazie aplikacji ani nie wywołują SMTP.

## Granice

Audyt sprawdza logikę i bezpieczeństwo aplikacji. Nie jest oceną aktualności przepisów, stawek VAT lub obowiązków KSeF/OSS; nie zastępuje księgowej ani zewnętrznego systemu fiskalnego. Nie zmieniano kwalifikacji podatkowych ani wartości stawek.

SMTP nie zapewnia dokładnie jednokrotnego doręczenia w razie zerwania połączenia po przyjęciu wiadomości przez serwer. Blokada usuwa wyścigi w aplikacji, lecz nie eliminuje tej niejednoznaczności zewnętrznego transportu. Numeracja dokumentu i dziennik dostawy pozwalają rozpoznać ponowienie. Nie wykonano dużego testu obciążenia produkcyjnego ani rzeczywistych wysyłek.
