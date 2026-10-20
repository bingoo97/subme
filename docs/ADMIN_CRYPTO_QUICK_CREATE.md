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
