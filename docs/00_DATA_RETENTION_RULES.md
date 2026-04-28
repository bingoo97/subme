# Data Retention Rules

Ten dokument opisuje automatyczne czyszczenie danych po stronie admina.

## Gdzie są przełączniki

- `Admin -> Settings`
- 3 osobne przełączniki:
  - `Auto-czyszczenie historii po 12 miesiącach`
  - `Auto-czyszczenie płatności po 12 miesiącach`
  - `Auto-czyszczenie wygasłych zamówień po 12 miesiącach`

## Jak to działa

- Worker Dockera uruchamia cyklicznie:
  - `dashboard-panel/scripts/prune_retained_data.php`
- Jeśli dany przełącznik jest `OFF`, ten typ danych nie jest usuwany.
- Jeśli dany przełącznik jest `ON`, w bazie zostają tylko dane z ostatnich 12 miesięcy dla tego zakresu.

## Zakres czyszczenia

- Historia:
  - usuwa rekordy z `customer_activity_logs` starsze niż 12 miesięcy

- Płatności:
  - usuwa stare rekordy z `crypto_deposit_transactions`
  - usuwa zamknięte requesty z `crypto_deposit_requests`
  - usuwa zamknięte requesty z `bank_transfer_requests`
  - aktywne requesty (`pending`, `awaiting_confirmation`, `pending_payment`, `awaiting_review`) nie są usuwane

- Zamówienia:
  - usuwa tylko rekordy z `orders`, które mają `status = expired`
  - kasowane są tylko wpisy starsze niż 12 miesięcy
  - aktywne, opłacone, oczekujące i świeże zamówienia nie są ruszane

## Reguła na przyszłość

- Jeśli retention ma objąć kolejne tabele, trzeba zaktualizować:
  - `dashboard-panel/bootstrap/application.php`
  - `dashboard-panel/scripts/prune_retained_data.php`
  - ten dokument
