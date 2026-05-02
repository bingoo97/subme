# Payment Request Status Lifecycle

Ten dokument opisuje aktualny model działania requestów płatności po ustaleniach dla panelu admina i UI usera.

## Cel

Rozdzielamy dwa różne przypadki:

- `expired`
  - request wygasł czasowo, ale admin może jeszcze sprawdzić explorer / przelew i zaakceptować spóźnioną wpłatę
- `cancelled`
  - request został anulowany ręcznie albo został przeniesiony do koszyka po dłuższym czasie
  - traktujemy go jako kosz do późniejszego usunięcia

Dzięki temu nie mieszamy już timeoutu z ręcznym anulowaniem.

## Statusy

### Otwarte

Do otwartych należą techniczne statusy:

- crypto: `pending`, `awaiting_confirmation`, `awaiting_review`
- bank: `pending_payment`, `awaiting_review`

UI admina pokazuje je wspólnie jako `Otwarte`.

### Wygasłe

Status:

- `expired`

To requesty, które przekroczyły `expires_at`, ale nie zostały jeszcze ręcznie zamknięte.

### Anulowane

Statusy koszyka:

- `cancelled`
- dodatkowo historycznie nadal mogą tu wpadać `rejected` i `failed`

UI admina traktuje ten koszyk jako końcowy stan do ręcznego usunięcia albo automatycznego cleanupu.

### Zatwierdzone

Statusy sukcesu:

- `confirmed`
- `approved`
- `paid`
- `completed`

UI admina pokazuje je jako `Zatwierdzone - do wypłaty`.

### Zarchiwizowane

Status:

- `archived`

To requesty już domknięte operacyjnie po wypłacie / przeniesieniu do archiwum.

## Przejścia statusów

### 1. Utworzenie requestu

Nowy request startuje jako:

- crypto: `pending`
- bank: `pending_payment`

### 2. Timeout requestu

Maintenance oraz wybrane widoki usera wywołują wygaszanie:

- otwarty request po przekroczeniu `expires_at` przechodzi do `expired`

W tym kroku:

- request nie jest jeszcze kasowany
- admin nadal może go zaakceptować z opóźnieniem
- dla zamówienia czyszczony jest `payment_method`, żeby UI nie blokowało wyboru nowej metody
- pending renewal nie jest jeszcze anulowany

### 3. Koszyk wygasłych

Z poziomu `expired` admin może:

- `Akceptuj`
- `Szczegóły`
- `Odnowienie`
- `Anuluj`

Znaczenie:

- `Akceptuj` pozwala uznać spóźnioną, ale realnie otrzymaną wpłatę
- `Odnowienie` tworzy nowy request z aktualnym kursem
- `Anuluj` przenosi request do `cancelled`

### 4. Ręczne anulowanie

Ręczne anulowanie przez usera albo admina:

- nie usuwa requestu od razu
- zmienia status na `cancelled`

To dotyczy:

- `/orders` po stronie usera
- `/cryptocurrency` po stronie usera
- akcji `Anuluj` w panelu admina

### 5. Auto-przejście wygasłe -> anulowane

Jeśli request jest w `expired` i przez 24h nic z nim nie zrobiono:

- maintenance przenosi go do `cancelled`

W tym kroku:

- dla renewals wykonywane jest anulowanie pending renewal powiązanego z tym requestem
- dla crypto status `cancelled_at` jest ustawiany automatycznie
- dla bank transfer runtime column `cancelled_at` jest dokładany automatycznie, jeśli stara baza go jeszcze nie ma

### 6. Auto-usuwanie anulowanych

Jeśli request jest w `cancelled` i minęły kolejne 24h od `cancelled_at`:

- request jest usuwany całkowicie z bazy

To jest twarde usunięcie.

## UI admina

## Filtry

W `page=payments` dostępne są:

- `Wszystkie`
- `Otwarte`
- `Nowe`
- `Wygasłe`
- `Anulowane`
- `Zatwierdzone - do wypłaty`
- `Zarchiwizowane - wypłacone`

Domyślny filtr:

- `Otwarte`

`Wszystkie` pokazuje literalnie wszystkie statusy, łącznie z archiwum.

## Akcje w tabeli

### Otwarte

Admin widzi:

- `Akceptuj`
- `Szczegóły`
- `Anuluj`

Nie używamy już osobnej ścieżki workflow opartej na filtrze `Weryfikacja`.

### Wygasłe

Admin widzi:

- `Akceptuj`
- `Szczegóły`
- `Odnowienie`
- `Anuluj`

To jest główny koszyk dla requestów po czasie, które nadal można ręcznie zweryfikować.

### Anulowane

Admin widzi:

- `Szczegóły`
- `Usuń`

Nad tym koszykiem jest warning, że usunięcie jest trwałe i należy go używać tylko wtedy, gdy klient na pewno nie zapłacił.

### Zatwierdzone

Admin widzi:

- `Szczegóły`
- `Archiwum`

### Zarchiwizowane

Admin widzi:

- podgląd / explorer
- szczegóły

## Edycja pojedynczej płatności

W edytorze płatności:

- `Akceptuj` działa także z `expired`
- `Anuluj` działa z requestów otwartych i wygasłych
- `Odnowienie` jest dostępne z `expired`
- twarde `Usuń` pokazujemy tylko dla `cancelled/rejected/failed`

## UI usera

### `/orders`

Aktywny request płatności:

- ma przycisk `Anuluj`
- kliknięcie przenosi request do `cancelled`
- pod przyciskiem pokazuje się czerwony hint, żeby nie anulować requestu, jeśli środki zostały już wysłane

Timeout requestu:

- request przechodzi do `expired`, nie do `cancelled`

### `/cryptocurrency`

Lista `Tickets` pokazuje historię requestów z poprawnymi etykietami:

- `Paid`
- `Archived`
- `Expired`
- `Cancelled`
- `Rejected`
- `Failed`

Usuwanie:

- user może trwale usuwać tylko requesty `Cancelled`
- `Expired` nie ma przycisku trwałego usunięcia

Nad historią pokazuje się warning, że usuwanie anulowanego requestu kasuje go całkowicie z bazy.

## Zachowanie dla zamówień i renewals

### Zwykłe zamówienie

Anulowanie requestu płatności:

- nie anuluje już samego zamówienia
- czyści `payment_method` na orderze, żeby można było wygenerować nowy request

### Renewal

Gdy request renewal:

- wygaśnie -> zostaje `expired`, admin nadal może go zaakceptować
- zostanie anulowany ręcznie -> pending renewal jest anulowany
- spadnie z `expired` do `cancelled` po 24h -> pending renewal też jest anulowany

## Techniczne uwagi implementacyjne

### Crypto

Crypto requesty używają:

- `cancelled_at` w tabeli `crypto_deposit_requests`

Manual cancel:

- ustawia `cancelled_at`, jeśli było puste

Expire:

- nie ustawia `cancelled_at`
- zmienia tylko `status` na `expired`

### Bank

Bank requesty historycznie nie miały `cancelled_at`.

Runtime helper:

- `app_ensure_payment_request_runtime_columns()`

automatycznie dodaje:

- `bank_transfer_requests.cancelled_at`

gdy aplikacja potrzebuje nowego lifecycle.

## Najważniejsze reguły bezpieczeństwa

- `expired` nie zwalnia od razu requestu do kosza, bo admin może jeszcze zaakceptować spóźnioną wpłatę
- `cancelled` to stan końcowy do usunięcia
- user nie może trwale usuwać requestów `Paid`, `Archived` ani `Expired`
- admin nie powinien trwale usuwać niczego poza koszykiem `Cancelled`

## Pliki objęte tą logiką

- `/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/bootstrap/application.php`
- `/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/admin/bootstrap.php`
- `/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/admin/index.php`
- `/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/pages/profil/orders_payment.php`
- `/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/pages/profil/payments_crypto.php`
- `/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/templates/profil/orders_payment.tpl`
- `/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/templates/profil/payments_crypto.tpl`

