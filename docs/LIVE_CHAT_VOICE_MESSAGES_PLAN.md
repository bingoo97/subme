# Live Chat Voice Messages Plan

## Cel

Dodać do live chatu po stronie usera i admina obsługę wiadomości głosowych w stylu zbliżonym do Signal:

- nagrywanie przez przytrzymanie przycisku mikrofonu
- maksymalna długość nagrania: `30 sekund`
- po puszczeniu przycisku nagranie wysyła się jako wiadomość audio
- nagranie można odtworzyć bezpośrednio w dymku rozmowy
- pliki audio są automatycznie usuwane po `24 godzinach`

To ma być osobna funkcja live chatu. Nie zastępuje zwykłych wiadomości tekstowych ani uploadu obrazków.

## Wniosek architektoniczny

Funkcja jest wykonalna w obecnym projekcie i ma sens produktowy, ale nie jest to mały patch UI. To jest pełna funkcja end-to-end:

- frontend web mobile/desktop
- upload pliku audio
- zapis wiadomości audio w istniejącym modelu chatu
- rendering audio po stronie usera i admina
- cleanup plików i rekordów po 24h

Ocena trudności:

- frontend: średni
- backend: średni
- całość: `6.5/10`

## Główne ograniczenia i ryzyka

### 1. Przeglądarki mobilne

Największe ryzyko to różnice między:

- Chrome Android
- Safari iOS
- desktop Chrome / Safari / Firefox

Nagrywanie będzie oparte o `MediaRecorder`, ale nie wszystkie przeglądarki zachowują się identycznie przy:

- `touchstart / touchend`
- uprawnieniach do mikrofonu
- formacie wyjściowym audio

### 2. Format pliku

Najbardziej realne opcje dla MVP:

- `webm`
- `ogg`
- ewentualnie `mp4/m4a` zależnie od browsera

Nie warto na start robić serwerowej konwersji do jednego formatu, jeśli nie ma takiej konieczności. Na MVP lepiej:

- zapisać oryginalny format z przeglądarki
- przechowywać `mime_type`
- renderować natywny `<audio controls>`

### 3. UX hold-to-record

Najprostsza wersja:

- przytrzymanie startuje nagrywanie
- puszczenie kończy i wysyła

Wersja bardziej premium:

- przytrzymanie startuje
- przesunięcie palcem w bok anuluje
- puszczenie wysyła
- pojawia się mały timer nagrania

Na start warto zrobić prostszy wariant.

### 4. Retencja 24h

Usuwanie nagrań po 24h powinno być spięte z istniejącym maintenance runnerem, nie z cronem “obok”.

To znaczy:

- audio ma `created_at`
- po 24h runner usuwa:
  - plik z dysku
  - payload wiadomości audio albo oznacza wiadomość jako expired

## Proponowany zakres MVP

### User UI

Po stronie usera:

- nowy przycisk mikrofonu obok pola wpisywania wiadomości
- przytrzymanie rozpoczyna nagrywanie
- timer pokazuje czas od `0` do `30s`
- po przekroczeniu `30s` nagranie kończy się automatycznie
- puszczenie przycisku:
  - kończy nagranie
  - wysyła plik

### Admin UI

Po stronie admina:

- brak nagrywania na start, albo opcjonalnie to samo co user
- obowiązkowo:
  - admin widzi otrzymaną wiadomość audio
  - admin może ją odtworzyć

Rekomendacja MVP:

- nagrywanie w pierwszej wersji tylko po stronie usera
- odtwarzanie po obu stronach

To upraszcza wdrożenie i szybciej daje wartość.

### Message bubble

W dymku wiadomości audio:

- ikona play
- pasek audio z natywnym playerem
- czas nagrania
- ewentualnie badge `Voice`

Na MVP nie trzeba robić waveformu. Wystarczy estetyczny, prosty player.

## Proponowany model danych

Nie trzeba budować nowej dużej tabeli, jeśli obecny live chat już przechowuje wiadomości i załączniki. Są dwie opcje.

### Opcja A: nowy typ wiadomości w istniejącej tabeli

Rekomendowana.

W istniejącej tabeli wiadomości dodać obsługę nowego typu:

- `message_type = 'audio'`

Oraz payload:

- `audio_path`
- `audio_mime_type`
- `audio_duration_seconds`
- `expires_at`

Jeśli obecna tabela ma tylko `message_body`, można:

- trzymać `message_body` jako pusty string
- dane audio trzymać w nowych kolumnach

### Opcja B: osadzenie specjalnego tokena w treści

Np. w stylu:

- `[[APP_CHAT_AUDIO]]...`

To jest gorsze od opcji A, bo:

- trudniejsze do walidacji
- trudniejsze do cleanupu
- bardziej podatne na bugi rendererów

Rekomendacja:

- iść w jawny `message_type = audio`

## Proponowane kolumny / migracja

Jeśli obecna tabela wiadomości live chatu nie ma odpowiednich pól, dodać:

- `message_type VARCHAR(...) DEFAULT 'text'`
- `audio_path VARCHAR(...) NULL`
- `audio_mime_type VARCHAR(...) NULL`
- `audio_duration_seconds SMALLINT UNSIGNED NULL`
- `expires_at DATETIME NULL`

Opcjonalnie:

- `is_expired TINYINT(1) DEFAULT 0`

Jeśli system już ma jakąś tabelę załączników, można rozważyć użycie jej, ale dla MVP prostsze będzie dopięcie tego bezpośrednio do wiadomości czatu.

## Struktura plików audio

Proponowana ścieżka:

- `/uploads/chat/audio/`

Nazewnictwo:

- `voice_{conversationId}_{timestamp}_{random}.{ext}`

Przykład:

- `voice_105945_1777495635_ab12cd34.webm`

To daje:

- łatwe debugowanie
- małe ryzyko kolizji
- prostszy cleanup

## Upload flow

### Krok 1

Frontend pobiera audio z `MediaRecorder`.

### Krok 2

Tworzy `FormData` i wysyła do endpointu uploadu live chatu.

### Krok 3

Backend:

- sprawdza sesję usera
- sprawdza uprawnienia do rozmowy
- waliduje MIME / extension
- zapisuje plik
- dodaje wiadomość typu `audio`

### Krok 4

Frontend odświeża rozmowę i nowy bubble pojawia się w oknie.

## Walidacja backendu

Trzeba bezwzględnie dodać:

- max duration `30s`
- max size pliku
- whitelist MIME types
- tylko zalogowany user/admin
- tylko do rozmowy, do której dana osoba ma dostęp

Przykładowe limity MVP:

- `max 2 MB`
- tylko:
  - `audio/webm`
  - `audio/ogg`
  - `audio/mp4`
  - `audio/mpeg` jeśli zajdzie potrzeba

## Cleanup po 24h

Najlepiej dodać nowy krok do maintenance runnera.

### Runner powinien:

1. znaleźć wiadomości audio z `expires_at < NOW()`
2. usunąć pliki z dysku
3. wyczyścić referencję do pliku lub oznaczyć wiadomość jako wygasłą

### Rekomendowany efekt w UI po cleanupie

Wiadomość zostaje w historii, ale zamiast odtwarzacza pokazuje:

- `Voice message expired`

To jest lepsze niż pełne kasowanie wiersza, bo nie rozwala chronologii rozmowy.

## UX rekomendowany dla MVP

### Zachowanie przycisku

- `touchstart / mousedown` -> start recording
- `touchend / mouseup` -> stop and send
- `mouseleave` podczas desktop hold -> stop

### Widoczne stany

- idle: ikona mikrofonu
- recording:
  - czerwony stan przycisku
  - licznik czasu
  - delikatna animacja
- sending:
  - loader

### Edge cases

- user puści po `0.3s` -> anulować za krótkie nagranie
- brak zgody na mikrofon -> alert
- browser bez wsparcia -> schować funkcję albo pokazać niedostępność

## Co nie jest potrzebne w MVP

Na start nie trzeba robić:

- waveformu
- edycji nagrania
- pause/resume recording
- drag-to-cancel jak w Signal 1:1
- transkrypcji
- serwerowej transkodacji ffmpeg

To można dodać później.

## Etapy implementacji

### Etap 1. Backend

- sprawdzić obecną tabelę wiadomości live chatu
- dodać `message_type = audio`
- dodać kolumny audio
- dodać endpoint uploadu audio
- dodać zapis wiadomości audio
- dodać cleanup w runnerze

### Etap 2. User UI

- dodać przycisk mikrofonu
- dodać nagrywanie hold-to-record
- wysyłkę audio
- render bubble audio

### Etap 3. Admin UI

- render bubble audio po stronie admina
- odtwarzanie audio
- opcjonalnie własne nagrywanie

### Etap 4. Polish

- mikroanimacje
- timer
- cancel gesture
- lepszy player
- wizualne wyróżnienie voice message

## Rekomendowane decyzje przed startem implementacji

Przed wdrożeniem warto potwierdzić:

1. Czy nagrywanie ma działać:
   - tylko po stronie usera
   - czy także po stronie admina

2. Czy po 24h:
   - usuwać całe wiadomości audio
   - czy tylko pliki i zostawiać placeholder

3. Czy MVP ma być:
   - bardzo proste i szybkie
   - czy od razu bardziej premium z gestami

## Rekomendacja końcowa

Najlepszy wariant wdrożenia:

- MVP tylko z nagrywaniem po stronie usera
- odtwarzanie po obu stronach
- `message_type = audio`
- cleanup po 24h przez maintenance runner
- placeholder po wygaśnięciu zamiast twardego usuwania wiadomości

To da:

- szybki realny efekt
- małe ryzyko
- dobrą bazę pod późniejszy upgrade do wersji “premium jak Signal”

## Checklist do startu implementacji

- potwierdzić, czy admin też ma nagrywać audio
- sprawdzić aktualny model tabel live chatu
- przygotować migrację SQL
- przygotować endpoint uploadu audio
- dodać cleanup do maintenance runnera
- zaprojektować bubble audio dla usera
- zaprojektować bubble audio dla admina

