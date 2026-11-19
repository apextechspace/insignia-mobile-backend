# Launch audit — 19 November 2026

## Verified receipts follow-up

The later assisted-payment follow-up adds the manager's monthly accommodation receipts card and a rooms-only, date-filtered statement with monthly chart, CSV and protected Print/PDF. It also aligns the owner dashboard with statement receipt accounting, clarifies partial-payment labels, adds branded guest lifecycle messages/WhatsApp drafts, prevents overlapping pending proof submissions, requires the exact remaining balance after a deposit, and preserves rejected proof with reviewer/reason/time. Old unused proofs are excluded from income and retained for audit.

Verification for that follow-up: **208 backend tests passed (1,344 assertions)**, plus **6 final guest-message checks (37 assertions)**, portal production build and ZIP checksum. A new nullable audit-field migration and rebuilt portal assets are required. No storefront release or seeder is needed. See `docs/RESERVATION_POLICY_HANDOVER.md` for the deployment sequence. The live-acceptance boundaries and separate legacy public-proof privacy follow-up below remain applicable.

## Result

The backend and booking app have passed the automated checks below after the audit fixes. They are ready for a controlled deployment and live acceptance test. This is not a guarantee that every possible defect is absent, or that the Namecheap configuration has been verified. No production database was accessed or cleared during this audit.

Scope: the checked-out Laravel backend and Expo booking app on `feature/guest-booking`. The separate marketing repository is not present in this workspace and was not included in this source review.

## Findings fixed in this release

| Finding | Correction |
| --- | --- |
| Owner booking creation could bypass the shared availability lock and accept an arbitrary total | Creation now locks the room, checks overlapping reservations/holds, calculates the server quote and creates a pending 15-minute hold. Existing room/date fields are read-only. |
| Manager review section lacked a consistent visual structure | Added responsive finance cards, cleaning reviews, maintenance arrivals and urgent incident cards; rebuilt the dashboard utilities. Dark mode now follows the portal's class-based theme. Narrow layouts have a single-column KPI arrangement. |
| Manager upcoming list omitted confirmed deposits without a full `paid_at` stamp | Includes confirmed stays with a verified assisted payment. Maintenance arrivals use a separate query so later arrivals are not hidden by the first twelve reservations. |
| Assisted guests could appear merely as “Guest” in manager booking lists | Uses the saved booking contact when available. |
| Portal payment alerts were not compatible with the Filament notification bell | Enabled owner/operations database bells and supplied Filament-compatible display data while retaining the existing mobile payload and business status. New notifications are supported; this does not rewrite historical notifications. |
| Guest maintenance/visitor alerts missed the actual owner role and responsible desk | Durable maintenance alerts go to owner, manager and front desk; visitor alerts go to owner and front desk. Push remains queued when a native token exists. Visitor wording no longer claims security has been notified. |
| Cleaner could access inventory through the API despite the portal restriction | API and app navigation now match the cleaner's limited role. Authorized management/front desk retain inventory access. |
| Browser `Alert.alert` calls were silent | Shared web dialogs now display errors and preserve confirmation/success callbacks; native dialogs remain native. |
| Native date picker had no functioning web implementation on legacy room/service forms | Added browser date/time controls with bounds checking, while retaining native pickers. Local room date keys no longer depend on UTC conversion. |
| Failed shop room loading invented delivery destinations | Removed hardcoded fallback rooms; failure shows an error/retry and prevents proceeding without a real room. |
| Payment retries on legacy commerce screens could create duplicate pending records | Reuses the created pending record within the payment screen; displayed success amounts use the server record. Backend verification remains authoritative. |
| Uploaded service proof could be presented as verified payment | Verification badge now requires the server's `paid_at` value. Removed fabricated personal wellness claims. |
| Auxiliary user opening the backend root was redirected to Operations and denied access | Root now sends auxiliary users to their own panel. |
| Custom quotation services could enter a zero-value checkout | App opens a prefilled WhatsApp quotation request for zero-priced services. Backend refuses zero-value service bookings. Nothing is automatically sent to WhatsApp. This is a manual quotation handoff, not a new quotation-management system. |

## Dashboard and flow coverage

| Role / flow | Checked behavior |
| --- | --- |
| Owner `/admin` | Resource indexes; room pricing/media/offers; owner-only bank/deposit policy; transfer verification; payment exceptions; financial statements, CSV and print; financial record editing restrictions. |
| Facility manager / VRL `/operations` | Room-only finance boundaries; reservations; verified assisted receipts and balance; arrivals/departures; readiness; incident creation; stock issues; property areas; asset CSV import; operating float/reconciliation/monthly statements. Auxiliary commerce is excluded. |
| Guest Relations `/operations` | Assisted reservations and guest details; receipt submission; full-payment check-in guard; checkout; incidents and handovers. Cannot approve owner settings or access owner/auxiliary commerce panels. |
| Cleaner `/operations` | Own assigned tasks and shifts; issue reporting; checklist completion. Cannot verify own cleaning, release rooms, manage areas/assets or access inventory/financial data. |
| Auxiliary `/auxiliary` | Verified paid food/shop/service queues and allowed fulfilment transitions; role separation from accommodation operations and owner finance. |
| Guest app | Public catalogs; email access verification and session ownership; date availability; two-person limit; held checkout; returning guest data; server pricing; payment callbacks; own purchase/history isolation; web dialog/date behavior. |
| Payments | Wrong amount/currency/reference/provider ID; invalid webhook; duplicate callbacks; old retry references; expired room holds; stock shortage after payment; verified deposits and balances; no duplicate ledger recognition. |
| Stay lifecycle | Overlap prevention including temporary holds; check-in blocked until full verified payment and room readiness; identity details; checkout creates cleaning task; supervisor release requires completed checklist. |
| Finance | Assisted receipts counted once and in their verification period; Nigeria date boundaries; operating float and approved expense boundaries; historical statement snapshots; owner-only print route. |

Automated coverage exercises important valid and invalid paths; it does not exhaust every possible input, browser, host or concurrent MySQL transaction.

## Verification evidence

- Complete backend suite: **194 passed, 1,242 assertions** against isolated SQLite in memory.
- Final targeted check after the auxiliary landing correction: **7 passed, 43 assertions** (including booking creation/overlap and paid-record editing restrictions).
- App TypeScript: `npx tsc --noEmit` passed.
- App regression tests: **8 passed** covering same-tab payment navigation, unsafe URL rejection, native payment callback, web alert callbacks/cancellation, browser date bounds and native picker preservation.
- Backend production Vite build passed; deployment ZIP regenerated.
- App production Expo export passed, **44 static routes**, configured for `https://portal.insigniacourts.com/api`; deployment ZIP regenerated.
- Manager widget rendered using synthetic records in a separate in-memory database. Desktop light and narrow dark layout were inspected. No persistent users, bookings or transactions were created by these previews.
- Existing PHP installation emits a duplicate OpenSSL-extension warning; it did not fail the tests/build. React test-renderer reports deprecation. Neither warning is evidence of a payment error.

## Outstanding live acceptance / release boundaries

1. **Legacy uploaded-proof privacy needs a follow-up.** Some older booking proof paths still use the public storage disk and direct URLs. Assisted POS proofs already use private storage. Public proof URLs are not an authenticated document store; do not upload sensitive bank statements or identity documents there. A compatible private download/preview migration, including historical files, is required before treating this as a fully private document workflow. This release does not move or delete existing proof files.
2. Deploy both matched repositories and the rebuilt ZIPs. Do not publish a source repository as a document root. Portal must serve Laravel `public`; stay must serve the exported app files. Check hard refresh and extensionless routes.
3. Verify a persistent queue worker or cPanel cron processes the default queue without an open SSH session. SMTP alone does not drain queued receipts. Check `queue:failed` and receive one fresh guest email automatically. `queue:restart` only signals workers; it does not start one.
4. Make one controlled live room payment and one commerce payment. Confirm the gateway verification, receipt, owner statement, correct auxiliary/operations visibility and actual settlement configuration. Separate ledger visibility does not by itself prove separate bank settlement accounts.
5. Test a staff deposit plus remaining balance, with manager verification enabled only if the owner chooses it. Upload is evidence, not bank confirmation. Verify against actual bank/POS records. Check that paid-in-full guests have no balance collection requirement and check-in is still blocked for unpaid balances.
6. Confirm the owner's chosen percentage/night-based deposit policy, cancellation wording, bank account and final room prices. The offer badge and VAT-inclusive label do not alter the entered nightly price. Existing agreed booking terms remain unchanged.
7. Phone/Safari and Edge-on-Mac live acceptance: scrolling on cards, bottom navigation labels/safe area, room selection, service date picker, retry errors and payment return. Local builds and component tests are not a substitute for these device checks.
8. Browser push is not implemented by Expo's native notification API. Portal database bells and email work independently; native push requires an installed app, permission and a registered valid token. Do not promise web push.
9. Automatic refund payouts, automatic no-show room release and Moorgen smart-lock integration remain paused. Quotation services are handled manually through WhatsApp. Do not represent these as automated features.
10. A guest maintenance submission alerts the team and remains in the owner maintenance queue; it does not automatically create/assign a separate operations incident. Manager/front desk can read the alert and record/assign the operational work. A unified maintenance workflow can be added later if desired.

## Safe cleanup — next stage, not executed

The property has already received live payments, so “clear everything” must not mean truncating transaction tables.

1. Back up the production database and uploads outside the web root, and verify the backup can be read/restored.
2. Preserve owner/staff accounts, rooms, pictures, catalog, policy/settings, stock and all genuine guest/payment/statement records.
3. Identify explicit test reservation/order IDs and payment references, including whether each involved real money. Produce a dry-run list of affected dependent rows before deleting anything.
4. Preserve genuine gateway evidence, verified receipts, ledger history and approved statements. Correct financial records through an auditable process rather than erasing them.
5. Review the concrete cleanup list before executing it. Do not use `migrate:fresh`, `db:wipe`, broad truncation or a demo seeder on production.

## Deployment

Use the existing `feature/guest-booking` deployment process. Backend: pull, Composer install, `php artisan migrate --force`, verify/extract portal asset ZIP, refresh config/views/routes as appropriate and restart existing workers. No seeder is needed for this audit patch. The previously delivered reservation-terms migrations still need to be applied if not already deployed.

App: pull `feature/guest-booking`, verify `releases/insignia-stay-guest-booking-preview.sha256`, extract the matching ZIP into the configured stay document root (including `.htaccess`), hard refresh and run the acceptance steps above. Preserve the previous release for rollback.
