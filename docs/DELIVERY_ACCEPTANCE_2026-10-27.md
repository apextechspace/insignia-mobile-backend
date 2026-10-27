# Insignia Courts — Delivery Acceptance Checklist (27 October 2026)

**Status:** Draft branches; not permission to deploy or activate live collection.

## Preconditions

- [ ] Record branch, PR, and latest successful Actions SHA before merging.
- [ ] Back up local/staging database and uploaded media.
- [ ] Verify PHP/Composer requirements and install dependencies using lock file.
- [ ] Check `APP_ENV`, `APP_DEBUG=false` (production only), `APP_URL`, DB connection, trusted HTTPS host, and `SANCTUM_STATEFUL_DOMAINS` as applicable.
- [ ] Confirm encrypted or secret credentials are never committed.
- [ ] Run `php artisan migrate:status` and `php artisan migrate --pretend` against the intended DB.
- [ ] Apply `php artisan migrate --force` only to approved staging/production.
- [ ] Confirm `php artisan storage:link`, writable public disk, queue worker/supervisor, cache and scheduler.
- [ ] Verify email/push notifications and log/queue failure monitoring.

## Acceptance sequence (test with real test accounts)

1. [ ] Guest registers/logs in; staff/owner log into their correct portals; unauthorized direct routes reject access.
2. [ ] Maintenance suite excluded from guest catalogue and cannot be booked.
3. [ ] Two guests may hold pending overlapping bookings but **only one** may have those overlapping dates confirmed; retest with two concurrent payments against a MySQL staging database.
4. [ ] Flutterwave **sandbox** initializes, returns, verifies reference, transaction ID, amount, currency; canceled and delayed payment do not show paid.
5. [ ] Replayed webhook and duplicate provider ID do not create two paid reservations or two accommodation ledger entries.
6. [ ] Bank transfer receipt stays pending; only IPL independently checks bank credit and approves; duplicate bank reference rejected.
7. [ ] Guest My Stays reflects IPL/MCL updated status after returning to screen.
8. [ ] MCL check-in requires paid reservation and ready room; check-out marks completed and room cleaning.
9. [ ] Check-out creates housekeeping task; incomplete checklist cannot finish; supervisor verifies readiness; occupied suites cannot be released.
10. [ ] Boutique, dining and concierge require verified payment before operational fulfillment; staff receive database notices.
11. [ ] Expense submitter cannot edit another user's or an approved expense; MCL cannot edit owner ledger.
12. [ ] IPL float transactions reject overdraft; check simultaneous requests on **MySQL**.
13. [ ] Monthly statements and daily reconciliation reject unauthorized edits and direct record URLs.
14. [ ] Verify mobile deep links in a real build, not only Expo Go; Android/iOS as applicable.
15. [ ] Verify room photos, receipts, PDFs, uploads and print/export behavior on staging HTTPS.
16. [ ] Confirm actual rollback plan and recovery from most recent backup before launch.

## Pending external prerequisites

- **Bank transfer:** Confirm exact official beneficiary account details and authorization to display them in the app; otherwise keep the manual payment option disabled.
- **Flutterwave:** User alone configures secret key, webhook secret hash and redirect settings. Sandbox completion must be personally confirmed.
- **Smart lock:** No real unlocking or automatic PIN feature may be represented as integrated until hardware vendor, compatible gateway and official API account are verified and tested.
- **Infrastructure:** Production hostname, backup retention, email/push credentials and queue worker settings require operator verification.
- **Financial statement:** Unified owner-level financial report, export correctness and settlement accounting remain a separate acceptance scope.

## Defect reporting

For each failed acceptance step capture: test role, booking/order ID (not card or bank secrets), expected result, actual result, relevant sanitized log, Git commit SHA and screenshot. Log as Jira defect and block launch when payment, authorization, room double-sale or unsafe access is affected.

**Go/no-go:** Green automated CI is necessary but not sufficient. A human must approve deployment after sandbox payment and real role-based staging workflows pass.
