# Guest booking and dashboard access

Implemented on `feature/guest-booking` in the backend and Expo repositories. Review before pushing or deployment. No production credentials or data are changed.

## Customer journey

Web visitors land at `/book-online` from the Stay root. They choose dates and 1–4 guests, select an available suite, and provide the lead guest's name, email and phone. Special requests are optional. Laravel checks availability and calculates the price. The browser must review any rate change before payment.

A visitor verifies their email using a six-digit code before creating the reservation. There is no password or separate registration form. Verification creates or reuses a customer identity in the existing users table. Only possession of the email code/link authorizes access; typing an existing customer's email does not expose or attach their bookings. Existing customer names/phones are not overwritten by sign-in requests. Per-booking lead guest details are saved separately.

The signed-in guest uses the existing booking, payment, shop, dining and service APIs. `/access-stay` sends a fresh sign-in email for returning guests. The email includes a code and a single-use link. Link opening alone does not authenticate; the visitor confirms sign-in with a POST. Secrets are in the fragment, removed from browser history on load, and only hashes are stored server-side. Access expires after 15 minutes, with five verification attempts and request throttling by IP and email. Customer email access cannot authenticate staff or owners. Customer tokens expire after 30 days and use the existing logout mechanism.

Optional password setup requires a verified customer and a passwordless session created within the last 15 minutes. Existing password login remains available. Native app startup is preserved; unauthenticated native room checkout can enter the guest journey too.

## Reservation holds and payments

New online reservations hold the suite for 15 minutes. Date searches, creation, payment initialization, manual approval, and verified settlement use the same overlap rule. Creation/initialization/settlement/manual approval lock the suite row before the booking on transactional MySQL. Adjacent checkout/check-in dates do not overlap.

Expired unpaid holds stop blocking availability without a queue worker or scheduled cleanup. They remain in booking history for audit and reconciliation. Guests can resume an active checkout from My Stays or release an unpaid hold. Paid stays cannot be released through this endpoint. A payment arriving after expiry or cancellation enters the existing finance reconciliation workflow, without confirming the stay or posting accommodation revenue. Replayed webhooks remain idempotent.

Existing historical pending reservations without holds remain intact; online payment initialization acquires a hold after checking availability. Existing manual-transfer workflows remain available in the existing app; the new web guest journey uses Flutterwave. A receipt upload still does not prove payment. Finance approval checks other active holds and confirmed stays.

The pending-booking email is distinguished from the verified receipt. Receipt emails link to Access My Stay, which requests a fresh sign-in credential. Operations and owner booking views show lead guest details, guest count and requests. These details can prefill check-in, but staff must still verify identity and actual arrivals.

## Files and boundaries

Backend: migration `2026_10_30_000001_add_guest_checkout_access`, `GuestAccessController`, `GuestAccessEmail`, `RoomAvailability`, existing booking/payment controllers and manual approval service, customer receipt template, owner/operations booking views, and API routes. The existing database and payment verification/ledger pipeline are reused.

Expo: `app/book-online.tsx`, `app/access-stay.tsx`, `app/set-password.tsx`, `services/guestAccess.ts`, room/booking service types, web startup, login/profile links, native checkout handoff and pending checkout resume.

Door unlocking is not implemented. Future lock credentials must be tied to verified check-in, assigned room and departure/revocation rules, with the actual lock provider's authentication requirements.

## Deployment after approval

1. Back up the database, deploy backend source and run `php artisan migrate --force`.
2. Optionally set `GUEST_FRONTEND_URL=https://stay.insigniacourts.com` (the default), and retain the existing Flutterwave web return URL.
3. Verify production SMTP: login emails are synchronous and must arrive promptly. Receipt emails still require the existing database queue worker/cron.
4. Run config/view cache rebuilds. Keep the scheduler running for daily `guest-access:prune`, which removes credentials expired for over a day. Hold expiration itself does not depend on the scheduler.
5. Deploy a newly approved frontend export with the existing `.htaccess` extensionless routes. Do not reuse the earlier release ZIP: it predates these changes.
6. Point the marketing site's Book Online button at `https://stay.insigniacourts.com/book-online` (the root also routes there).
7. Confirm receipt delivery, returning sign-in on another browser, guest order ownership and approved gateway test/live behavior before opening real bookings. Review room rates before real charges.

Local browser verification uses a separate `storage/app/guest-preview.sqlite` database and array mailer, with no outbound emails. Automated gateway tests use mocks; they do not make live charges.

## Verification completed

- Full backend regression suite: 140 tests, 712 assertions passed.
- Focused final guest/payment/hold suite: 17 tests, 107 assertions passed, including the subsequently added end-to-end guest payment, password freshness and hold-release tests.
- TypeScript check passed; production export generated 44 static routes. Production API URL and new route files checked in the exported bundle.
- Local browser: date availability and lead guest details, email-code prompt, expired-code rejection, successful sign-in to the existing customer dashboard, and navigation back to booking. Checked phone and desktop widths.
- New frontend archive: `releases/insignia-stay-guest-booking-preview.zip`, with a matching SHA-256 manifest. It is separate from the older deployed release.

Live SMTP delivery, actual gateway settlement, cross-device email-link delivery, and MySQL concurrency on the hosting environment remain deployment verification steps. The code uses the established room-row locking order; SQLite tests do not simulate simultaneous MySQL transactions.
