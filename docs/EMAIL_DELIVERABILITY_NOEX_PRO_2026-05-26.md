# noex.pro Email Deliverability Changes

Date:
- 2026-05-26

Goal:
- improve transactional email deliverability for `noex.pro`
- reduce false-positive spam filtering for password and contact emails
- document the exact live changes applied on the hosting account and in app code

Reason for the change:
- cron and SMTP were working correctly, but recipient-side delivery still failed for some addresses
- a live bounce from Proton returned `554 5.7.1 rejected by rspamd filter`
- the message was marked as `sent` in `outbound_email_queue`, which only confirms SMTP acceptance by the sender host
- raw outbound mail did not contain a `DKIM-Signature`, even though the main domain had a valid Email Deliverability status in cPanel

Code change deployed:
- commit `ebe7e21` added application-level DKIM signing support in `dashboard-panel/bootstrap/application.php`
- the mailer now reads optional DKIM runtime settings from `~/.subme-secrets/<app-slug>/`
- this makes DKIM signing independent from whether the shared hosting SMTP layer signs mail correctly

Runtime files created on the server:
- `~/.subme-secrets/main-panel/dkim.private`
- `~/.subme-secrets/main-panel/dkim.selector`
- `~/.subme-secrets/main-panel/dkim.domain`

Live server actions completed:
- generated a dedicated private DKIM key for the application runtime
- configured selector `appmail`
- configured domain `noex.pro`
- deployed updated app code to `noex.pro`
- sent a live smoke-test email through the app mailer after deploy

DNS changes applied remotely:
- `_dmarc.noex.pro TXT "v=DMARC1; p=none; rua=mailto:support@noex.pro; adkim=s; aspf=s; fo=1; pct=100"`
- `appmail._domainkey.noex.pro TXT "v=DKIM1; k=rsa; p=..."`

Important DNS fix during rollout:
- the first `appmail._domainkey` write via `uapi DNS mass_edit_zone` corrupted `+` characters in the public key into spaces
- the selector record was corrected and re-saved so the published public key matched the private key on the server

Verification completed:
- `https://noex.pro` remained online after deploy
- public DNS now returns the upgraded DMARC record
- public DNS now returns the dedicated `appmail._domainkey` selector
- the published `appmail` public key matches the public key derived from `~/.subme-secrets/main-panel/dkim.private`
- a live smoke-test message stored in `/home/noexdvtf/mail/noex.pro/support/new/1779832916.M251847P2932604.server302.web-hosting.com,S=3731,W=3807` contains:
  - `From: Noex PRO <no-reply@noex.pro>`
  - `Reply-To: Noex PRO <support@noex.pro>`
  - `DKIM-Signature: v=1; d=noex.pro; s=appmail;`

Effect of the change:
- outbound mail from the app is now DKIM-signed at the application layer
- DMARC is now better prepared for reporting and stricter alignment checks
- this materially improves authentication posture for Gmail, Outlook, Proton and similar receivers

Remaining note:
- `outbound_email_queue.status = sent` still does not guarantee inbox delivery, because a remote provider can reject or spam-folder the message later
- after DNS propagation, the next practical step is to re-test a real transactional email to Gmail, Outlook and Proton and inspect the final authentication result in raw headers
