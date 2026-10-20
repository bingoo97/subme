# Quick creation of customer crypto payments

Implemented locally on 2026-10-20. No deployment or SQL migration in this change.

The empty **New** category in the topbar crypto flyout offers **Add new payment**. A three-step wizard stays inside that flyout:

1. Search and select a customer using existing customer-card results.
2. Choose a balance top-up or an order payment.
3. Enter the top-up amount (existing manual range: 1–200 panel currency units), or choose a new product / existing unpaid order, then select an available cryptocurrency and create the ticket.

Existing-order assignment covers initial, unpaid orders only. Paid subscriptions continue through the established renewal flow in Orders. Product prices and existing-order totals come from the server; package amounts are not restricted by the manual top-up range. Currency symbols are displayed when available.

The existing wallet allocator prefers the customer's assigned wallet and otherwise uses the available pool, respecting configured sharing rules. No available wallet/rate means no asset choice; users with wallet permissions see Add wallet. Opening or searching the wizard does not assign wallets. Ticket creation reserves only one assignment and expires after 60 minutes.

The ticket and optional new order are committed together. New orders remain unpaid, unfulfilled and without an expiry until activation. The established acceptance, balance and order-fulfilment workflow remains responsible for settlement. An existing-order ticket uses that order's customer, currency and server amount. Approval is never implicit in creation.

The new search actions require Payments access; order actions additionally require Orders access. Creation is POST + CSRF protected with a per-customer session cooldown. Customer and wallet locks prevent simultaneous submissions from generating duplicate tickets/orders. A detected or review-required payment blocks a replacement even after its sending deadline. Link failures roll back both records. Browser failures never automatically repeat a creation POST; the list is refreshed instead.

Search is debounced by 300 ms, starts at two characters and aborts superseded reads. Options are fetched only after customer selection, reuse the shared rate cache and use bounded order results. Creation returns updated New-category HTML/counts, replacing the list and topbar badge without navigation.

## Verification

- `python3 dashboard-panel/tests/run_crypto_quick_create.py` creates an empty disposable local Docker schema, seeds synthetic fixtures/cached rates and installs a fixture-only rollback trigger. Its `finally` block removes the schema and cache. No production data, remote chain calls or real payments are used.
- 52 creation, endpoint, authorization, CSRF, price, linking, rollback, concurrency and wallet reuse assertions.
- Existing crypto lifecycle suite: 69 assertions; existing quick-payment frontend cache/badge/mutation/countdown/acceptance suites passed.
- 67 complete application page renders without PHP warnings.
- Browser tests at 320×568, 390×844, 768×1024 and 1440×900. Created synthetic new-order, existing-order and top-up tickets through the actual local endpoint backed by a disposable database. Verified immediate list/badge updates, 60-minute remaining time, no-wallet message/link and no horizontal wizard overflow.

## Manual verification after the sending deadline

The receipt state is independent of the 60-minute sending deadline. A recorded positive incoming amount remains open for administrator verification; expiry cleanup repairs older open receipts that still carry a deadline. The quick flyout, reminders, customer payment screen and status endpoint agree on receipt detection, including legacy expired receipts. Customer cancellation and stale Expired delete actions cannot discard a detected receipt.

The scanner also revisits expired requests from the last 24 hours, behind current open tickets, using the existing per-run network budget/row limit and 120-second recheck interval. Its transaction-history lookback can cover the request's age, but matching still requires a verified incoming transaction within the original payment window. Delayed discovery is not permission to match unrelated older transfers. OFF only records the receipt and queues manual verification; it never credits the customer. Partial receipts follow the same rule. Already detected OFF receipts are not repeatedly scanned.

Recent expired customer screens retain the existing visible-tab-only 30-second status watcher so a later detection can replace the screen automatically. No chain queries are performed by this customer status endpoint. Extended crypto lifecycle regression suite: 82 assertions, including late discovery, old-deadline repair, partial transfer, no premature credit, category classification, cancellation/deletion guards and preservation days later.

## Kreator na stronie Payments

Strona Payments i okienko krypto używają wspólnego partiala `admin/partials/crypto-create.php` i niezależnych instancji tego samego skryptu. W Payments kreator jest dostępny także na telefonie, poza ukrywanym opisem workflow. Przy wyłączonych płatnościach krypto przycisk nie jest renderowany.

Po zapisie lista Payments jest odświeżana jednym żądaniem GET z zachowaniem aktualnych filtrów; odświeżają się również liczniki filtrów oraz okienko i badge topbara. Potwierdzenie zawiera link do konkretnej płatności i listy Nowe. Niepewny wynik zapisu nie powoduje automatycznej ponownej wysyłki. Odświeżone wiersze nadal obsługują modal akceptacji. Kreator nie dodaje cyklicznego pollingu.

Weryfikacja lokalna: scenariusze doładowania, nowego i istniejącego zamówienia, ponowne otwarcie oraz cofanie kroków; podgląd 320, 390, 768 i 1440 px; testy endpointów na izolowanej bazie oraz pełne renderowanie stron. Bez zmian danych produkcyjnych.

Dodatkowa regresja przeglądarkowego zapisu: `node dashboard-panel/tests/payments_create_frontend_regression.js` — zachowanie filtrów, odrzucanie spóźnionych odpowiedzi, bezpieczne nieudane odświeżenie i modal akceptacji w nowych wierszach.

## Audyt pełnego workflow — 2026-10-20

Rozszerzono zestaw do 86 sprawdzeń. Obejmuje wspólną blokadę duplikatu w obu kreatorach, czyste odpowiedzi JSON po wyłączeniu krypto i dla nieaktywnych klientów, otwarcie administratorowego ticketu w rzeczywistym handlerze klienta, zatwierdzenie tylko raz, oczekiwanie na realizację, realizację i archiwizację bez ponownego księgowania. Sprawdzono też częściową wpłatę, ponowienie dla tego samego zamówienia po anulowaniu i odnowienie wygasłego ticketu za pakiet przekraczający limit ręcznego doładowania.

Poprawki:
- Kwoty w tabeli i podglądzie płatności używają symboli walut także dla starszych rekordów zapisujących kod jako symbol.
- Ukryta nieprawidłowa kwota doładowania nie blokuje walidacji po przejściu do płatności za zamówienie. Limit kwoty jest jawny przy polu.
- Bieżący timeout pobierania opcji pokazuje błąd zamiast pozostawiać „Pobieranie…”. Stare odpowiedzi są ignorowane.
- Otwarta płatność ma bezpośredni link do szczegółów, a niepewny wynik zapisu daje dostęp do listy i odświeża ją bez powtarzania POST.
- Utworzenie ticketu w topbarze odświeża również otwartą listę Payments jednym GET; utworzenie z Payments synchronizuje oba widoki.
- Nowe i przypisane zamówienie zapisuje metodę crypto w tej samej transakcji. Akceptacja wpłaty nie wykonuje realizacji u providera.
- Częściowo opłacone zamówienie zachowuje status nieopłaconego i dostaje informację o dopłacie zamiast komunikatu o zakończeniu.
- Zastępowany nieopłacony ticket pozostaje wygasły; nie trafia do archiwum oznaczającego rozliczenie. Wspólne porządkowanie po utworzeniu/odnowieniu zachowuje anulowane zgłoszenia przez 72h, a zapisanych wpłat nie usuwa tą ścieżką.

Testy używają odseparowanego schematu MySQL, fikcyjnych portfeli, zapisanych lokalnie kursów i transakcji testowych. Nie wysyłają środków ani wiadomości do prawdziwych klientów. Bez deployu.

## Shared wallets and customer links

The creator now honours global sharing for assigned addresses even when their old `is_reusable` flag is zero. Historical payments of another customer do not forbid an additional assignment when sharing is ON; OFF retains the existing exclusive-assignment/history restrictions. The creator still excludes addresses serving an open payment to prevent competing tickets on the same address. An empty shared-mode chooser explains temporary availability/rate constraints instead of claiming that the wallet pool is empty.

Customer handles link to the Users profile both in search results and after customer selection. The Add wallet link uses the standard green btn-success style. Verified in the local browser, including its computed colours and the retained selected-customer profile URL. Regression suite: 95 checks, including ON/OFF sharing, history reuse, no duplicated addresses/assignments and rejection of a concurrent competing ticket. No live payments or deployment in this change.
