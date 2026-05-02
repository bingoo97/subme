# Same-Order Subscription Extensions

## Goal

If a customer extends the same subscription from the same provider, the system should keep one `order` record instead of creating a new order every month.

This keeps the UI cleaner and preserves a single timeline for one subscription.

## When to keep the same order

Keep the same `orders.id` only when all conditions are true:

- the existing order is a `subscription`
- the order belongs to the same provider
- the customer is extending or renewing that same subscription flow
- the previous order was already paid
- the selected extension product is an active, non-trial subscription package

## When to create a new order instead

Create a new order when:

- the customer buys a different service/provider
- the customer buys credits
- this is not an extension/renewal of the current subscription
- the existing order is not eligible for self-extension

## Storage model

### Main order

The main `orders` record remains the canonical subscription record.

It stores:

- current product
- current amount
- current expiry
- current access status

### Renewal history

Renewal history is stored in `order_renewals`.

Runtime columns used by the extension flow:

- `product_id`
- `duration_hours`
- `target_expires_at`
- `payment_request_type`
- `payment_request_id`
- `renewal_note`

## Customer flow

### 1. Customer clicks Extend / Renew

Customer is redirected to the payment page for the same order:

- `/order-payment-{order_id}?renewal=1`

No new `orders` row is created here.

### 2. Customer chooses package and payment method

The selected extension package is stored as a pending renewal row in `order_renewals`.

At this moment:

- the original order is not extended yet
- the original `expires_at` is not changed yet
- this prevents fake extension visibility before payment approval

### 3. Customer creates crypto/bank payment

The payment request is linked to:

- the same `order_id`
- the pending renewal row in `order_renewals`

If the payment request is cancelled or deleted before approval, the pending renewal is marked as cancelled too.

### 4. Customer pays from balance

Balance payment applies the renewal immediately:

- debit balance
- extend the same order
- save renewal history

No new order is created.

## Admin approval flow

When admin approves a crypto or bank payment linked to an order:

1. the payment still credits customer balance first, like the rest of the system
2. if a pending renewal exists for that payment/order, the system:
   - debits the same amount back as `order_extension`
   - applies the renewal to the same order
   - updates expiry
   - updates product/price/currency
   - writes extension history
   - writes activity log

This preserves compatibility with the existing balance runtime accounting model.

## UI rules

### Customer orders table

- active subscriptions with `<= 7 days` left show a red credit-card button
- expired subscriptions still show the renewal/payment button
- clicking the subscription title expands extension history inline

Example:

- `Extend: 2026-04-08 06:33:55`
- `Extend: 2025-10-03 12:05:37`

### Modal

The modal can also show the same extension history list.

## Logging

Each applied extension writes:

- `order_renewals` row with `status = applied`
- `order_status_events` note about extension
- `customer_activity_logs` entry with `order_extended`

## Safety rules

- no extension is shown as applied before payment approval
- cancelled/deleted payment requests cancel the pending renewal record
- same-provider validation is mandatory
- credits can never use this flow
- trial products can never use this flow

## Important implementation note

This flow is intentionally separate from the old legacy behavior where `order_extend` created a brand-new order row.

For V2 schema, extensions should always prefer:

- one canonical order
- many `order_renewals` history entries

instead of many cloned orders for the same subscription.
