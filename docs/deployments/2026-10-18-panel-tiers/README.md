# Panel tiers — local readiness audit, 2026-10-18

Status: locally verified and ready to prepare a deployment to demo.subme.pro and dashboard.subme.pro. No hosting files, databases or settings were changed by this audit. This is a code manifest, not a record of a completed deployment.

## Requirement evidence

| Requirement | Current evidence |
| --- | --- |
| Configurable tiers; every paid subscription counts separately | `panel_tiers_regression.php`: default/custom boundaries, multiple orders per customer, retained paid-through history despite status changes, reversal and deletion |
| One outstanding invoice, 30-day renewal eligibility, threshold restriction | Real concurrent child processes; unique open-slot constraint; one/three-month and eligibility tests |
| Add the purchased calendar-day period exactly once | Full, partial, overpaid, top-up and concurrent settlement tests; month lengths, leap year and DST; immutable quoted days |
| BTC, SOL, DOGE and USDT Ethereum verification | Cached raw-shaped network responses pass through the production parsers/verifiers and settlement; invalid destination/token, failed and insufficiently confirmed transfers do not grant access |
| Below 90% requires top-up; 90–100% grants proportional whole days | Exact native-unit boundary tests for 6/8/9-decimal assets, aggregation of top-ups, duplicate rejection |
| Expiry and confirmations after 00:00 | Immediate notice transition, continued polling, on-time mempool observation followed by late mining/confirmation, finality cutoff, unknown late transfers retained for review |
| Cancel and return to the same coin | Original invoice/rate/period/deadline restored without requiring a fresh market quote; canceled receipts never grant the newer invoice; explicit-ID review closure |
| Emails to active login `admin`, toggle and failure recovery | Transactional queue/deduplication; real application email worker sends to loopback SMTP; receipt includes transaction/date; delivery failure retries without reversing paid access |
| OFF and authenticated operations | OFF scanner performs no scan; authenticated enabled page renders; method, permission, CSRF and stale-response tests |
| Bounded DB/network load | Shared scan lease, cached network data, transport budget; frontend pause when closed/hidden/offline and bounded retry; running local maintenance worker reports panel-payment step OK |
| Responsive UI | Seven viewport sizes, full ERC-20 address and 48-hour top-up countdown, 11 state variants at desktop/320 px, settings ON/OFF on phone/desktop; no horizontal overflow found |
| Existing pages/workflows | 62 subscription, 50 order-extension, 178 notification checks; 30 full admin/customer page renders without PHP warnings; 54 general crypto payment/finality/concurrency assertions passed |

Final module suite: **225 backend assertions and 47 frontend assertions passed**. Tests use disposable databases and a separate crypto cache. Fresh module DDL and repeated schema initialization are covered. The main local database and its administrator-configured settings are preserved.

Visual viewports: 1920×1080, 1366×768, 1024×768, 768×1024, 390×844, 320×740, 844×390. UI state tests use the actual PHP view/CSS/JS with isolated example API responses; they do not create real invoices or payments. States include choice, pending, partial, confirmation waiting, paid, review, canceled review, canceled, expired, near-tier limit, too early and no configured wallets.

## Deployment procedure for the next authorized deployment

1. Use `files.txt` only and reverify `manifest.sha256` immediately before packaging. The changed existing runtime files were reviewed against the last documented runtime deployment, `b06d95cea15b7f472f6b87495a5199b8c4868f19`. Do not export the entire dirty working tree.
2. Check each target independently: its own backend pointer, database identity, PHP runtime, mysqli/cURL, writable crypto cache, configured SMTP and existing one-minute maintenance cron. Dependencies already present in the baseline include the shared crypto verifiers and migration 041 email deduplication.
3. Back up the target runtime files and its database before changes. Preserve `config/mysql.php`, environment files, deployment pointers, .htaccess, cron, sessions, uploads, customer data, provider settings and existing panel paid-through date.
4. Upload new helpers/views/assets before the existing callers, using a controlled maintenance/atomic release procedure. Run migration 042 in that target's own database, then initialize through `app_panel_tiers_ensure_schema()` (also invoked by Settings). It inserts only a missing module configuration with automation OFF and the target's currency; existing configuration is retained. Never import local table rows, wallets, orders or seed SQL.
5. Verify file hashes, anonymous endpoint rejection, authenticated Dashboard/Settings/Orders, OFF behavior, asset loading and the panel-payment maintenance step. Compare protected configuration/data fingerprints to the backup. Keep the provider remote-login module OFF as previously requested.
6. Configure dedicated receiver addresses separately for each installation and check currency/prices before intentionally enabling automatic panel payments. Reusing customer-deposit addresses or the same panel receiver across installations is unsupported. Configure the active `admin` account email and test the target SMTP separately.
7. After deployment, verify one controlled real payment and its actual email receipt before relying on live collections. Local verification uses chain fixtures and loopback SMTP; it cannot prove live explorer availability, hosting cron or external SMTP delivery.

Rollback: restore the previous runtime snapshot; preserve module tables/transfer claims and the paid-through date for reconciliation. Do not drop financial records or re-credit a receipt. There is no runtime vendor/composer update in this manifest; the locally bundled QR library includes its MIT license.

The local maintenance cycle also reports an unrelated pre-existing warning: the swapper payout executor is not configured. Its panel-subscription payment step succeeds; this module does not configure or activate swapper payouts.

## Follow-up: administrator identity security

Only the active, full-access account whose stored login is exactly `admin` can enter Settings Admin Mode. Other accounts receive HTTP 403 for direct GET/POST `admin=true` requests. The owner login cannot be renamed; `admin` in any letter case is reserved for account creation and renaming.

Administrator management reloads the actor and target roles from the database. A Boss can edit/reset/delete lower-role accounts, and edit their own profile without changing their own role/status. Peer/higher-role accounts cannot be edited, demoted, have their recovery email changed, reset or deleted. This includes the owner attempting a peer Boss password reset. Only the owner may create a new peer-role administrator; normal Bosses can assign lower roles only. These restrictions prevent first demoting another Boss and then resetting their password.

Every administrator can change their own password through the navbar key dialog, including roles without Settings access. The server requires the current stored password, applies the existing length/repeat validation and ignores submitted target IDs. The administrator-management form cannot bypass this self-password check.

`admin_identity_security_regression.php`: **51 assertions passed**, including real Settings renders and GET/POST handlers, direct Admin Mode requests, forged actor/target IDs, CSRF, protected account/hash snapshots, self changes and subordinate resets. Tests change credentials only in disposable test databases, never actual administrator accounts. Module suite was rerun: 225 assertions passed. Runtime manifest/archive hashes refreshed after these changes. No hosting deployment performed.

## Follow-up: Crypto settings and retired Rango module

LI.FI, Hinkal and Swapper are now in an Admin Mode-only Crypto tab immediately after Features. All moved inputs/selects/textareas explicitly belong to the existing `adminSettingsFeaturesForm`, including the Crypto submit button. A save from either tab includes both groups and retains hidden-tab values. The switches for provider remote login and automatic provider subscriptions are displayed only in Admin Mode; the normal handler always preserves their current stored values regardless of submitted input.

The standalone Rango UI, settings parsing/validation/save fields, enablement helpers, runtime schema creation, CSS, JSX entry and bundle have been removed. Existing database columns are deliberately left inert; no data-dropping SQL is required. The two obsolete deployed files in `deletes.txt` must be removed from their respective public/backend roots after backup when deploying this release. The generic LI.FI SDK and its supported routing providers remain part of LI.FI; they are not the retired standalone Rango widget.

The extended isolated suite passes **69 assertions**, including real-handler round trips from both tabs, preserved crypto/feature values, provider-switch visibility and malicious POST rejection. Crypto controls were also inspected in a real browser at 1366, 390 and 320 px; every moved named field retains form ownership, toggles expand the correct bodies, and there is no page/pane horizontal overflow. Existing local settings were not changed by these tests. Deploy payload now includes the two locale files; refresh hashes and apply the explicit deletion manifest as well as installing runtime files.

## Follow-up: customer crypto overview in the topbar

The avatar button now opens the existing self-password dialog; the separate key button has been removed. A matching wallet button immediately to the right of Notifications opens a read-only customer-payment overview, only when crypto payments are enabled and the user has Payments access.

Filters show the latest ten matching customer deposit requests: New (open requests whose deadline has not passed), Expired (explicitly expired or elapsed open requests), Cancelled, and To withdraw (approved, unarchived requests). The latter shows actual received amounts when available and explicitly says current wallet balance must be checked separately. No Swapper, Hinkal, platform-tier payments or transfers are included, and no withdrawal is initiated by this view. Each item opens the existing payment detail page. The footer uses the existing crypto list filter.

Loading is lazy: ordinary page rendering performs no new payment query. A GET endpoint checks authentication, Payments permission, enabled crypto, a fixed filter allowlist and a two-second per-filter session throttle. Each data lookup uses one SQL statement returning at most ten joined records. There are no blockchain/price calls and no background polling. The frontend caches each filter for 30 seconds, supports explicit refresh, times out after 12 seconds, aborts superseded/closed requests and ignores stale responses. Resizing does not refetch. Output is escaped; decimal crypto amounts retain their string precision. Locale is captured before releasing the session lock.

Desktop uses the established notification flyout style; at up to 767 px it fills the viewport and locks background scrolling. The avatar remains available on small screens. Browser checks cover 1366×900, 390×844 and 320×740, all four filters, full viewport dimensions, ten visible-list records, body lock/unlock, avatar password opening and header tool fit. Example HTML/responses come from disposable test fixtures.

Final combined backend suite: **89 assertions passed**. New frontend suite: **20 cache/race/offline/cancellation/responsive-state assertions passed**. Thirty complete admin/customer page renders also passed without PHP warnings, with unchanged SQL counts on ordinary pages. No live payments or administrator passwords were changed. The five new runtime files are included in the code-only manifest; hosting has not been updated.


## Local follow-up: payment guidance and compact actions

All four customer crypto overview filters now use compact icon/title/help cards with matching tones. Inactive filter tabs share a neutral background; only the active tab is coloured. Payment rows show date, blue handle and adjacent status, without a visible ID. Approved customer crypto deposits have a black “Przenieś do Archiwum” action and a separate explorer link, using the existing CSRF-protected archive workflow. Main payment detail acceptance is right-aligned with an animated double chevron; verified crypto archive actions use the black animated treatment. Reduced-motion preferences disable the effects.

Every payment detail editor (crypto, bank and legacy crypto top-up) now explains its selected status directly beneath the select. Guidance describes what it means and when to use it, distinguishes legacy top-ups and bank transfers, warns that cancelling does not refund and archiving does not transfer funds, and provides a neutral fallback for unknown historical statuses. Changing the select immediately updates the tone, icon and explanation and shows an unsaved-preview notice. Returning to the original status hides that notice. Existing programmatic blockchain-check changes also refresh the guidance. No new query, request, polling or save is introduced. The check action label is now “Sprawdź explorer” / “Check explorer”; its function is unchanged.

Validation: **133 backend assertions**, **23 crypto overview frontend assertions**, **22 payment status preview assertions**, PHP/JavaScript syntax checks. Browser verification of dynamic approved/expired guidance at **320×740** and **1366×900**, with no horizontal overflow. Earlier 30 full admin/customer render checks passed without PHP warnings. No actual payment was accepted or archived in UI tests; no hosting deployment or live data migration was performed.


## Local follow-up: topbar crypto priority badge

The wallet button shows the full count for the first non-empty category in this order: New (amber), Expired (red), To withdraw (green), Cancelled (rose). All zero counts hide the badge. Opening the flyout selects that priority category; manually changing filters while open remains possible. Fresh overview responses update the badge and next-opening priority. Accessible button labels include category and count.

This supersedes the earlier zero-query header statement: the crypto-enabled header with Payments permission now uses one aggregate SQL query over actionable customer crypto requests, with no joins, archive/history scan, external calls or polling. Conditions are shared with the list filters, including elapsed pending requests. The flyout still limits rows to ten but the badge counts all matching requests. Each uncached/explicit overview load adds the same aggregate count query to the existing bounded list query. Cancellation guidance states the existing cleanup rule: 72 hours after cancelled_at, at the next cleanup run; the cleanup workflow is unchanged.

Validation: 142 backend assertions; 23 overview frontend assertions; 18 priority/count/default-filter assertions. Verified amber count 11 with ten displayed rows, priority tab opening, cancellation guidance and no horizontal overflow at 320 px. No production deployment or live payment changes.


## Local follow-up: compact payout actions menu

Customer crypto payout rows now have a single three-dot button using the live-chat header-more styling. A collapsed, labelled action group contains icon rows for Archive (neutral charcoal) and Explorer (blue). Only one group is open; repeated click, outside click, filter changes and flyout close collapse it. Escape collapses the group and returns focus before closing the enclosing flyout. The group expands within the list flow so the final row is not clipped by the scroll container. Standard archive form, CSRF token, explorer URL, permissions and backend behavior remain unchanged. No extra requests or queries.

Validation: 143 backend assertions, 23 overview frontend assertions, 22 priority/menu frontend assertions. Real rendered fixture checked at 320×740 and 1366×900, no horizontal overflow, 40 px action targets on narrow phones, Escape keeps enclosing flyout open. No live payment mutation or deployment.


## Local follow-up: standard auto-accept switch and restricted test options

The crypto auto-accept ON/OFF switch is now visible and saveable in standard Settings for administrators with Settings access, without Admin Mode. Advanced amount/window parameters remain restricted and preserved on normal saves. Homepage client/reseller test switch, page guidance/homepage overview and payment-test warning are now rendered only in Admin Mode; the existing server-side preservation prevents forged normal POSTs changing them.

When automatic crypto acceptance is OFF, the New overview hint adds a recommendation and a direct link to standard Settings, Features, focused/scrolled to the auto-accept toggle. The recommendation/link are absent when ON; the link is hidden in other overview filters. Hash navigation overrides the remembered Settings tab but does not change or save any option.

Validation: 153 isolated backend assertions, 25 overview frontend assertions, 22 badge/menu frontend assertions, PHP/JavaScript syntax checks. Browser link verification selected Features, focused an enabled toggle outside Admin Mode and confirmed all three test switches absent. No horizontal overflow at 320 px. Actual user settings were not changed; no hosting deployment.


## Local follow-up: responsive topbar refinement

Unified the topbar control sizes, muted borders, compact search typography, avatar separator/truncation and badge alignment across admin routes. Height follows shared layout offsets: 72 px desktop, 68 px tablet, 64 px phone. Mobile keeps every control on one line, uses a white borderless search field without a magnifier and a 14 px “Wyszukaj...” placeholder on narrow phones (input text stays 16 px). Right-hand control spacing is 8–10 px; only widths tighten at 320–360 px. Desktop search retains its fuller placeholder; matchMedia updates it without requests. No two-row or expanding mobile search remains in the final implementation.

Validation: actual localhost topbar geometry checked at 320, 360, 375, 390, 575, 576, 768, 991, 992, 1024, 1366 and 1920 px; one-line alignment and no overlapping controls. Shared header checked on Orders, Dashboard, Payments, Users, Products and Settings at 320/768/1366. Mobile search returned results and reset correctly; both notification flyouts fit at 320 px. Final 320 px browser verification confirmed white background, zero border, hidden magnifier, 14 px placeholder and 64 px header. Frontend suites passed: search 12, crypto overview 25, badge/menu 22 assertions; PHP/JavaScript syntax and scoped whitespace checks passed. No deployment or change to payment workflows.

Topbar polish: utility buttons are exact circles (40 px desktop / 38 px phone); avatar hidden below 992 px. Browser checked 320/390/768/991/992/1366 px for equal dimensions, 50% radius, correct avatar breakpoint and fit. One-line layout and offsets preserved.

Final topbar details: narrow-screen menu/search gap is 12 px (verified fit at 320 px). Utility controls have transparent backgrounds, no border or shadow, and a soft #f2f4f7 background for hover/focus/active/open states; circular geometry and category badges remain intact. Browser verified transparent base state and grey expanded state.

Small-screen global search results: outer result/status card has no border, radius, top margin or shadow at up to 991 px. Browser verified zero values and no horizontal overflow at 390 px; desktop retains 1 px border, 16 px radius and 16 px margin.


## Local follow-up: compact phone search results

At up to 767 px, reduced result header, row fonts/padding, avatars, badges and wallet logos. Orders use a single separated list like users; left ID column is hidden and no decorative left icon is rendered. Mobile expiry is a plain localized date instead of the progress visual; dates, amount colours, delivery indicators, notes and two existing actions remain. Empty notes and redundant paid explanatory text are omitted in the phone presentation. Long titles retain full tooltips. Wallets use compact coin/address rows with ellipsis and a full-address title. Desktop retains its table, ID and progress visuals. No new data requests or database work. Native search cancel decoration is suppressed to avoid a duplicate clear icon.

Browser checked real results with users, active/awaiting orders, a support note and a wallet at 320/390/575 px and desktop. No horizontal overflow; phone ID/progress hidden, desktop ID/progress visible; order action menu opens and closes with Escape. Search frontend 12 assertions and PHP lint/whitespace checks pass. No payment/order mutations or deployment.

Phone search order follow-up: only the three-dot action button is shown, in a dedicated left column. The separate preview button is hidden on phones; the title remains a details link. Handles are blue, and the existing awaiting-activation PAID badge moves from the title next to the expiry date. Desktop presentation remains unchanged. Browser verified at 320/390 px: menu opens, preview hidden, correct blue handle, badge beside date and no overflow.


## Final search layout on all screens, explanatory tooltips and coherent badges

The compact phone layout is now the shared search presentation on desktop/tablet too, superseding earlier desktop-table notes: one left menu, title/handle/date, amount on the right, no visible ID/preview button/progress. Title links still open details. Delivery indicators sit 5 px after the product title. Visible support notes are escaped amber chips with a note icon. A localized status chip distinguishes fulfilled, awaiting payment, awaiting activation, expired, cancelled, refunded and other states, with terminal states taking precedence. Status/paid chips share soft backgrounds, subtle borders, 20 px height, 10 px font and 5 px radius. Paid label uses normal case. No database writes or external requests were added.

Search delivery indicators and locale flags use the Orders-style Bootstrap tooltip (plain text, viewport boundary, hover/focus, keyboard-focusable targets). Instances are created for dynamically loaded results, disposed on query/reset/replacement and hidden on scroll/resize/Escape, avoiding orphaned tooltip nodes.

Validation: 160 isolated backend assertions (including paid, unpaid, awaiting activation and terminal status precedence); 14 search frontend assertions including tooltip initialization/disposal. Actual browser checks at 320/390/768/992/1366 px confirmed no overflow, removed ID/preview/progress, correct icon spacing, status/note chips and tooltip content. Changing search left zero orphaned visible tooltips. PHP/JS syntax and scoped diff checks pass. Local only, no live payment/order mutation or deployment.

Desktop content spacing: .admin-content top padding reduced from 28 px to 16 px at >=992 px. Verified Dashboard/Orders/Payments content begins immediately after the 72 px topbar; only inner top padding changed.


## Floating crypto payment menu and circular more buttons

Payout action groups now use a fixed overlay anchored to their trigger, with measured bounds inside the visible list/viewport. They open above when space below is insufficient, and close on list scroll/resize as well as the existing dismiss paths. Rows never expand; left spacing before the trigger is 12 px. Crypto more buttons are constrained to 30×30 px at all widths; live-chat more buttons retain 32×32 desktop / 40×40 phone, 50% radius and no flex shrinking.

Validation: both crypto frontend suites pass 25 assertions, including upper/lower placement and scroll dismissal. Browser verified identical 60.5 px payment row height before/after opening at 320/390/1366 px, accessible unclipped overlay and no overflow. Computed button dimensions/radius verified for live chat and crypto on phone and desktop. Backend actions unchanged; no deployment or live payment mutations.


## Crypto actions in every category

All customer-crypto categories now use the circular three-dot trigger and non-expanding floating menu. New requests link to review in the existing payment detail and can cancel. Expired requests can open details, renew if the persisted status allows it, or cancel. Cancelled requests can open details or renew. Payout retains Archive. Explorer appears wherever a valid supported wallet address is available. A pending request classified as elapsed does not expose renew until its actual status permits that existing workflow. Detail/review links use a neutral #f7f8fa surface; explorer stays blue, renew amber, cancel rose, archive charcoal. Forms retain existing CSRF, Payments authorization, action handler and redirect scopes; no new backend mutations/API were introduced.

Validation: 174 isolated backend assertions including each menu category, appropriate actions, no direct accept and elapsed-pending renewal exclusion. Crypto frontend suites 25/25 pass. Browser checked each category at 320 px: correct actions, menu within viewport and unchanged row height. No real mutation, no deployment.

Cancelled overview hint now says requests are removed 72h after being marked cancelled and asks to verify no funds reached the address before deletion. Cancelled menus end with a white/red-outline Delete action using the existing CSRF-protected delete handler and confirmation prompt; other categories do not expose it. 179 backend assertions pass; rendered fixture confirmed the hint, final action ordering, outline and confirmation without submitting deletion.


## Payment detail top-up badge and action toolbar

Replaced the bright Bootstrap info top-up badge with a muted wallet-icon chip, subtle blue-grey surface, small regular label and fine border. Quick actions now share aligned icon/text spacing, 36 px desktop height and quiet secondary styles; acceptance remains green and rightmost, withdrawal archive remains charcoal with the existing attention effect. Phone layout uses an equal two-column grid with 40 px targets; a single action spans both columns. No control names, payloads, permissions or mutations changed.

Validation: 179 backend assertions passed after markup changes; PHP lint and scoped diff checks pass. Browser inspected pending payment at 320/390/768/992/1366 px: no overflow, matching button heights, acceptance on the right. Approved fixture has a full-width single archive button on phone. No actual payment action submitted and no deployment.


## Expired crypto request deletion

Expired hint adds the option to move an unpaid request to Cancelled and wait longer, or delete it after verification. Expired menus end with the existing outlined Delete/confirmation action and retain the expired redirect scope. Crypto deletion now atomically rechecks status/deadline in the DELETE: cancelled/rejected/failed/expired or elapsed open requests are eligible; approved and unexpired open requests are not. A payment_approval credit event blocks deletion even if its status was changed manually. Existing order renewal cleanup, wallet release and audit behavior follow a successful deletion. Bank/legacy deletion behavior unchanged. No background workload added; this check runs only on explicit deletion.

Validation: 186 disposable-database backend assertions, including real POST/CSRF and successful expired/elapsed deletion, approved/unexpired/previously credited refusal; both crypto frontend suites 25 assertions pass. Browser verified appended hint, last outlined Delete action, confirmation and fitting overlay. No actual payment removed, local only.


## Prevent clipped crypto action menus

Desktop flyout reserves up to 580 px minimum height within the available viewport and uses a viewport-aware maximum. The list can shrink independently. Action-menu placement now uses the entire flyout bounds instead of restricting its height to the payment-list rectangle; full menus can overlap other flyout content without enlarging payment rows. On exceptionally short windows, the menu has its own accessible vertical scrolling.

Validation: 186 backend assertions; overview frontend 25 and positioning/priority frontend 26 assertions. Fixture includes an expired payment with an explorer address to reproduce all five options. Browser: 1366×900 and 1024×768 show five options with no inner scrolling/clipping; 844×390 and 320×568 also fit. At 800×320, menu stays within the flyout and viewport and keyboard navigation scrolls the final Delete option fully into view. No payment action submitted, local only.


### Unified status and payment badge corners
- Shared `--admin-badge-radius: 5px` across notifications, payment details, crypto quick view, user/product status badges and global search. Includes top-up labels, notification amounts and references; circular action controls and navbar counters are unchanged.
- Verified on localhost at desktop and 390px mobile widths: notification status/type badges all compute to 5px.


### Dashboard mobile payment action
- Below 768px the access renewal strip stays in one row, with an icon-only 38px action on the right and a translated accessible name. Desktop retains its text label. Verified at 320px, 390px and 1280px.


### Payment status copy
- Approved crypto status explains the receiving wallet and archiving after withdrawal. Removed the unsaved-status preview caption; status explanations and colors still update without saving. Frontend guidance regression: 15 assertions passed; PHP/JS syntax valid.


### New crypto request acceptance menu
- Added a green, gently pulsing modal action above Payment details in New. Uses existing crypto acceptance modal and guarded POST handler, CSRF, current request amounts and order identity; initial menu action is type=button and performs no approval.
- Lazy-rendered actions use delegated modal opening. Locale messages captured before session close. Request list still uses one bounded query; no additional queries or network calls during list rendering.
- Verified modal opening at desktop and 320px without approving a real payment. Regression: 189 isolated backend checks, 10 modal delegation, 25 list and 26 badge assertions passed.


### Crypto acceptance amount visibility
- Hide received crypto/FIAT fields and rounding hint while loading or when no matching request transfer exists. A detected or previously recorded receipt shows them; provider errors retain manual verification. Acceptance is disabled while fields are hidden. Reset preview state and avoid focusing hidden inputs on reopen.
- Verified empty request in local modal; 24 visibility assertions and 10 modal delegation assertions passed.


### New crypto ticket waiting indicator
- Added a small rotating ring beside New and red bold remaining minutes below the creation date, calculated from the actual UTC expiry. Tickets without expiry show no invented deadline. Countdown updates only in an open New flyout, resets on cached render and stops on close/category switch. Expiry shows Deadline passed and hides the ring. No additional SQL queries, polling or external requests. Reduced motion respected.
- Verified local mobile appearance; 11 fake-clock countdown assertions passed.


### Correct ticket timezone and independent payment detection
- Corrected quick-view countdown: request DATETIME values use the application runtime timezone, not UTC. Chain transaction timestamps retain their UTC interpretation. One-hour expiry regression verifies the exact epoch offset. No stored request data changed.
- Maintenance now detects verified, unambiguous incoming transfers with auto-accept OFF, preserving existing finality, receipt/token/address, amount/window, deduplication and network-budget checks. OFF records actual received amounts and awaits administrator review without balance credit or order fulfilment. Its send deadline is cleared after receipt; detected manual tickets are excluded from repeated scans. ON uses existing idempotent approval and credit flow.
- New flyout has a green detected-payment annotation. Only an open, visible flyout refreshes cached DB data once per minute; no blockchain calls on flyout reads. Customer payment/renewal views replace send/QR/cancel instructions with a receipt-pending-verification message.
- Customer status checks use the existing check_payment endpoint in a read-only GET mode: active session ownership, validated ID, per-session throttle, one bounded joined read, no provider calls. Visible payment pages check once per 30 seconds; reload on detection/approval, with offline/hidden skips, timeout and overlapping-request protection. Legacy POST behavior retained.
- Isolated regressions: 68 payment/finality/concurrency/auth/client checks, 34 preview checks, 190 admin/quick-view checks; frontend clock, status polling, visibility/cache suites passed. Changed Smarty templates compile successfully. No live payments accepted or local data copied to a server.

- Customer topbar also displays detected/pending administrator verification without an expired-payment countdown. Customer checkout workflow regression: 75 assertions passed. Detected quick-view rows display actual received amounts.


### Acceptance modal cancellation after one hour
- Removed footer Close. Existing header close remains. A red cancellation form appears only for cancellable requests more than 3600 seconds after their runtime-zone creation timestamp, including when the hour passes while the modal is open. Uses existing authenticated/CSRF-protected quick action and the selected request ID; never submits merely by opening. Disabled during acceptance to prevent simultaneous user actions.
- Explorer button now has an external-link icon after its label.
- Verified expired local ticket modal without cancelling or approving anything. Boundary test covers exactly 60 minutes, over 60 minutes, non-cancellable states and missing dates. Admin regressions: 190 passed.


### Payment navigation and equal modal decisions
- Compact quiet outline back navigation and circular related-actions menu. Mobile navigation aligns left.
- Acceptance and cancellation use equal grid columns: 180x42px desktop; 175x44px at 390px and 140x44px at 320px. Header close remains. No action submitted during verification.


### Customer full and partial payment scenarios and history
- Added an isolated six-case receipt matrix (100/90/50 EUR on a 100 EUR request, auto-accept OFF/ON at an 80% acceptance threshold). All OFF cases await review with zero credit. ON credits actual 100/90 EUR; confirmed 50 EUR receipts await review. Partial detection retains wallet/time/finality/receipt/deduplication checks and does not lower the configured auto-approval threshold. Manual acceptance of 50 EUR credits exactly 50 once.
- Customer receipt screens show actual amount and shortfall. Approved history uses actual received crypto/FIAT amounts, not requested amounts, and explains shortfalls. Approved receipt confirmation replaces the new-payment prompt. Cancellation warning appears only when a cancelled ticket is present.
- Refined history typography, amount hierarchy, status badges, wallet/explorer row and underpayment hint. Archived is neutral; cancelled is explicitly red. Existing links, deletion forms/CSRF and pagination preserved. Scope is customer crypto history/receipt cards.
- Six-case matrix, manual partial approval/idempotency, template rendering and mixed history statuses: 36 checks passed. Existing payment suite 68 and preview suite 34 passed. History cards fit 320px without horizontal overflow. Saved screenshots show actual rendered customer templates using synthetic fixtures; no real payment was sent or accepted.


### Private support annotation during order extension
- Selecting an extension package reveals an optional private support annotation. It appends to the current support note within the extension update, including paid pending renewals; existing notes are preserved. No annotation is exposed in customer renewal history. Invalid, failed and repeated extensions do not append it.
- Isolated extension regression: 58 checks passed. Mobile layout verified without extending a real order. Local changes only.


### Pre-deployment performance audit — 2026-10-19 (local only)
- Core asset initialization reads all configured assets in one batch and only repairs missing/incomplete definitions, avoiding nine redundant UPSERTs per request; preserves custom names, activation and quote currency.
- Sidebar open-payment badge uses indexed status counts rather than full history aggregation. Isolated fixture: 3 open plus 3000 archived requests; same result as full summary, EXPLAIN estimates 3 rows.
- Admin inbox bounds group rows before fetching group summaries. Pinned global group ordering and the disabled-global-group case are covered.
- Topbar state endpoint releases the session lock before notification queries, allowing other requests in the same session to proceed.
- Whole maintenance cycle uses a non-blocking database-scoped advisory lock with finally/release, preventing concurrent manual and cron cycles from executing the same jobs simultaneously. Existing crypto network budgets, per-request scan cooldown, cache and finality checks retained.
- Found status-width mismatch: awaiting_confirmation is 21 characters while the column allowed 20. Added migration 043 and canonical schema width 32. Applied width-only change locally; no existing payment state/amount/date modified. Apply 043 independently on both hosting databases before deployment; do not copy local data.
- Isolated verification: 31 performance/concurrency checks, 190 admin authorization checks, 68 crypto payment/finality/concurrency checks, 55 customer Messenger checks and 30 full page renders without PHP warnings. Frontend polling, quick-view and chat race suites passed. No real funds, messages or email delivery used for testing.
- Page-render timings are local fixture measurements, not a production load guarantee. Orders still render per-row detail modals, so their HTML is larger; replacing these with lazy loading would be a separate workflow change.
