# Payment and notification launch readiness — 29 October 2026

Status: implementation in progress; live collection and hosting acceptance are not yet verified.

Local verification: backend PHPUnit suite passes (93 tests, 393 assertions), including receipt rendering with embedded logo, escaping, unpaid receipt guard, webhook replay routing, manual receipt reference and push ticket failures. Mobile `npx tsc --noEmit` passes. A sample rendered receipt is available locally at `storage/app/receipt-preview.html`. No SMTP messages, device pushes or real gateway charges were sent during these checks.

## Where work stopped

The 27 October acceptance checklist remains the baseline. Existing code includes Flutterwave server verification, frozen checkout amounts, duplicate provider/reference protection, payment reconciliation, independently reviewed room bank transfers, and paid-only room/commerce fulfillment. The current checkouts also contain earlier uncommitted pricing and operations fixes; preserve those when preparing a release.

## Notification delivery

| Event | Customer | Staff |
| --- | --- | --- |
| Pending room request | Branded email explicitly awaiting payment | Database alert and mobile push |
| Verified room, dining, boutique or concierge payment | Insignia HTML email receipt, database alert, mobile push | Database alert and mobile push |
| Finance-approved room bank transfer | HTML receipt with verified bank reference, database alert, mobile push | Database alert and mobile push |
| Rejected room bank receipt | Database alert and mobile push; no payment receipt | Database alert and mobile push |

Staff routing currently targets admin, IPL finance admin, delivery partner / operations and guest relations. Individual service assignment and generic staff accounts are not part of this routing. Push requires a registered Expo token; browser users have database alerts, not browser push. The receipt embeds the existing Insignia logo as an email attachment so it does not depend on public image hosting.

Email and push use the database queue independently. For verified payments their jobs are inserted with the settlement transaction using the same default database connection. Do not set `DB_QUEUE_CONNECTION` to a separate database: that would lose transaction atomicity. Provider retries are bounded; inspect failed jobs and retry after fixing the cause. Email delivery cannot guarantee exactly-once delivery if an SMTP server accepts a message but its acknowledgement is lost.

## Configuration still needed

- SMTP: set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME="Insignia Courts"` privately on the host. Use the mail provider's prescribed scheme/port. The current local mailer logs mail and has no SMTP username/password.
- Production: `APP_ENV=production`, `APP_DEBUG=false`, correct HTTPS `APP_URL`, production database and stable `APP_KEY`. Keep `.env` outside the public document root.
- Mobile/web builds: set `EXPO_PUBLIC_API_URL=https://<backend-host>/api` before building. Production builds reject missing or non-HTTPS API URLs. Rebuild native apps to register the `insignia` callback scheme and notification plugin.
- Native push: configure the Expo/EAS project ID, Android/iOS application IDs and push credentials, then verify registration and delivery on physical devices. Current app configuration has no EAS project ID.
- Flutterwave: confirm the intended test/live credentials privately and register `https://<backend-host>/api/payments/flutterwave/webhook` with the matching secret hash. Verify the hosted checkout callback in a signed native build. Native returns use `insignia://payment-complete`. Browser returns require `FLW_WEB_RETURN_URL` set to the exact public HTTPS storefront `/payment-complete` URL; other callback URLs are rejected.

## Namecheap staging gate

1. Confirm the hosting plan supports the PHP version and extensions required by `composer.lock`, Composer, MySQL, HTTPS, scheduled tasks and outbound SMTP/HTTPS. Do not assume a shared plan supports a persistent worker.
2. Back up database and uploaded media. Deploy a reviewed source release (do not reuse the old root `backend.zip`), install locked dependencies and build backend assets. Point the backend document root at Laravel's `public` directory.
3. Configure environment, writable `storage`/`bootstrap/cache`, storage symlink, migrations and config cache. Keep the app out of debug mode.
4. Run `php artisan queue:work database --sleep=3 --tries=3 --timeout=60` under a supported process manager. If only cron is available, configure a non-overlapping once-per-minute `queue:work database --stop-when-empty --max-time=50 --timeout=40` run and accept the resulting delivery delay. Monitor `php artisan queue:failed` and application logs; restart workers after deployment. Never expose a public URL to run queue commands.
5. Run the baseline acceptance checklist against staging MySQL, including concurrent room payments, invalid amount/currency/signature, canceled checkout, duplicate webhook, bank approval/rejection and role boundaries.
6. Verify customer receipt rendering in actual email clients, logo, dates, amount/reference, pending wording, staff database alerts, native push and SMTP failure/retry behavior.
7. Verify public HTTPS room photos/uploads and payment return links. Obtain operational acceptance, then configure live credentials and perform an approved small live payment and settlement check.

Deployment, SMTP delivery, device push, real MySQL concurrency and live payment settlement are outstanding until demonstrated in the target environment.
