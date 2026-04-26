# Email Notification Rules

Goal:
- keep the number of emails low
- send only high-signal notifications
- avoid duplicating the same event in multiple emails
- keep every email plain text only

Source of truth:
- templates: `email_templates`
- translations: `email_template_translations`
- queue: `outbound_email_queue`
- delivery worker: `dashboard-panel/scripts/send_outbound_email_queue.php`

Locales:
- user-facing emails use the customer `locale_code`
- active now:
  - `en`
  - `pl`
- future locale:
  - `de`
- admin template editor exposes `EN` and `PL` bodies/subjects separately

Active user-facing emails:
- `account-activation`
  - meaning: account created / account ready
  - sent after successful registration
  - plain text with login link only
- `password-reset`
  - sent after password reset request
- `payment-request-created`
  - sent when customer generates a bank transfer or crypto payment request
  - body must stay generic
  - no order id
  - no package name
  - no internal payment details
  - only short info plus login link
- `order-paid`
  - sent when payment becomes confirmed
- `order-activated`
  - sent when subscription becomes active
- `live-chat-customer-notify`
  - sent only when admin writes and customer is offline
  - 15-minute cooldown per conversation recipient
- `news-broadcast`
  - sent when admin creates a new active public/customer news entry
  - should stay generic and point customer to `/news`
- `account-blocked`
  - sent when admin changes customer status into `blocked`

Support/admin emails:
- `live-chat-admin-notify`
  - sent when customer writes on live chat
  - skipped when at least one active admin is currently online
  - 15-minute cooldown per customer inbox burst
- `support-payment-request-notify`
  - sent when customer starts the payment process
  - used as the lightweight “customer wants to buy / started payment” signal

Disabled by design to reduce noise:
- no automatic email on raw order creation
- no automatic email on subscription extension
- no automatic email on subscription expiry
- no automatic email on order cancellation
- no duplicate live chat email bursts while the same conversation is active

Anti-spam rules:
- all emails are plain text only
- stable sender identity is used
- `From` should use the SMTP account address
- `Reply-To` may point to support address
- headers include:
  - `Auto-Submitted: auto-generated`
  - `X-Auto-Response-Suppress: All`
- queue deduplication prevents repeated notifications in short windows
- customer live-chat email is skipped when presence says customer is online
- admin live-chat email is skipped when admin presence says someone from the active admin team is online

Operational note:
- real deliverability still depends on valid SMTP plus SPF, DKIM and DMARC for the sending domain
