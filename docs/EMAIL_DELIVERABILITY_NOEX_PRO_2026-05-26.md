# noex.pro Email Deliverability Changes

Date:
- 2026-05-26

Scope:
- improve outbound email authentication for `noex.pro`
- reduce false-positive spam filtering on transactional messages
- document remote changes made on the hosting account and in app runtime

Changes applied:
- added app-level DKIM signing support in `dashboard-panel/bootstrap/application.php`
- prepared deploy runtime to read DKIM secrets from `~/.subme-secrets/<app-slug>/`
- planned remote DNS update for `_dmarc.noex.pro`
- planned remote DNS update for a dedicated application DKIM selector

Runtime files used on the server:
- `~/.subme-secrets/main-panel/dkim.private`
- `~/.subme-secrets/main-panel/dkim.selector`
- `~/.subme-secrets/main-panel/dkim.domain`

Target DNS state:
- `_dmarc.noex.pro TXT "v=DMARC1; p=none; rua=mailto:support@noex.pro; adkim=s; aspf=s; fo=1; pct=100"`
- `appmail._domainkey.noex.pro TXT "v=DKIM1; k=rsa; p=..."`

Verification checklist:
- deploy updated app code to `noex.pro`
- verify DNS zone contains upgraded DMARC record
- verify DNS zone contains `appmail._domainkey` public key
- send a test message and confirm `DKIM-Signature` exists in the raw message
- re-test a transactional message flow after DNS propagation

Notes:
- `outbound_email_queue.status = sent` only means the SMTP server accepted the message
- a later remote rejection can still arrive as a bounce to `no-reply@noex.pro`
- Proton previously rejected test and password emails with `554 5.7.1 rejected by rspamd filter`
