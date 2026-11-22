# Clear prelaunch test activity

Use only while **all activity is test data**. This command is not a routine production reset. It does not run `migrate:fresh`, truncate tables, delete users or reset record IDs.

## Retained

Rooms, final prices, offers, photos and other uploaded files; users/passwords/roles; products, menus/categories and services/variants; assets and handovers; property areas; consumables and current stock quantities; manual bank account versions; deposit policy; migrations; application configuration and credentials. No default/demo seeder is run.

Current stock quantities become the opening baseline: this does not guess quantities before test purchases or restocking. Count stock physically and correct it through the existing inventory/product tools before launch. Test users are retained so staff logins cannot be accidentally lost. Remove specific unwanted guest accounts separately after review.

## Cleared

All bookings, assisted receipts, payment attempts/exceptions/manual reviews, accommodation ledger rows, food/shop/service orders and fulfilment history; expenses, operating-float transactions, daily reconciliations/monthly statements; incidents, maintenance requests, gate registrations, tasks, shifts and stock-movement history; notifications and queued/failed jobs; temporary guest access, tokens, password-reset tokens and sessions. Everyone must sign in again. Historical receipt files remain private/on disk, but no longer have active transactional records.

Room status/housekeeping notes are preserved unless `--rooms-ready` is passed. That option means the operator has checked physical readiness; it sets every room status to `available` and clears housekeeping notes. It does not change the separate `is_available` switch, room pricing, offers, descriptions or media. Do not pass it if a room is actually dirty or under maintenance. If retaining a cleaning status, assign a fresh cleaning/checklist task for supervisor verification before releasing the room.

## Server procedure

1. Pause the cPanel queue/scheduler cron and stop any persistent workers. Keep guests and staff out during cleanup. Do not leave a worker running with `--force`.
2. Pull the release and run normal migrations, then preview:

```bash
cd ~/portal.insigniacourts.com
git pull --ff-only origin feature/guest-booking
php artisan migrate --force
php artisan launch:clear-test-data
```

3. Enter maintenance, signal existing workers to exit, confirm they have stopped, and clear:

```bash
php artisan down
php artisan queue:restart
php artisan launch:clear-test-data --execute --workers-stopped
```

If every room has been physically checked and is ready, use `--rooms-ready` on that last command. Do not run cleanup twice to change room statuses later; use the normal room workflow instead.

The command prints a private SQL data-backup path and SHA256 before deleting rows. Backup creation must complete successfully. It exports every table and retains the current migrated schema. The backup is not exposed via `public/storage`; do not copy it into a public folder or Git. Also retain the normal hosting/cPanel backup. An offline restore into the **same schema** can be performed by the database administrator using this SQL file; it replaces all table data with the snapshot, so never import it over subsequent live activity. Uploaded files are untouched by cleanup and are not inside the SQL backup.

Deletion uses one transaction with foreign keys enabled; a constraint error rolls back deletion. MySQL/MariaDB affected tables must use InnoDB. Only database/sync queues and unprefixed MySQL/MariaDB/SQLite databases are supported. An unknown future dependency fails instead of silently cascading through a changed schema. Failure leaves the app in maintenance for investigation; do not assume cleanup succeeded or re-open automatically.

4. After a successful cleanup:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Re-enable the queue/scheduler cron. Confirm the owner and staff can sign in, all six rooms/prices/photos are correct, catalogues/settings remain, no test bookings or ledger rows appear, operating float/receipt statements start at zero, and room readiness/stock counts match reality. A preview-only repeat is safe. Any new live transaction after reopening must be kept.

This deletes application records only. It cannot undo payments, refunds, provider transaction history or emails already sent through Flutterwave, a bank or SMTP. Keep old transaction IDs unreused; do not reset AUTO_INCREMENT or rerun seeders. Smart locks, City Ledger spending and other deferred functionality are separate work.

## A retained cleaning status has no checklist

Room Readiness Review shows the latest checklist status and an Open Checklist link. Its confirmation action displays a persistent blocker notification if release fails, or a success message when the room becomes available. Successful checklist verification records the supervisor and verification time.

Task edits also display model validation beside the form and in a persistent notification. The word Done is a toggle label, not proof that the toggle is on. For room/area cleaning, every item must be switched on before saving Completed; incomplete submissions retain the previous stored status. Saving the completed task still does not release the room until supervisor review.

If a test room remains in cleaning after cleanup, do not rerun cleanup or bypass readiness checks. As owner/facility manager, open Room Status → Edit → **Create Readiness Checklist**. This is available only when a cleaning room has no turnaround checklist. It creates a pending inspection, not a completed or verified task. Under Tasks, inspect the room, check each item and mark the task completed. Then use Room Readiness Review → **Verify & Release**. The room becomes available without changing its price, images or catalogue availability switch. Existing checklists and checked-in guests block recovery; continue the normal task/checkout workflow for those rooms. Blocked status edits now show a persistent notification and an inline validation message.
