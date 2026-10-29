# Pre-push workflow audit — 29 October 2026

This review covers the Laravel backend/portals and Expo customer app. Automated checks reduce regression risk; this is not a claim that every possible defect or live integration has been eliminated.

## Completed fixes

- Added reservation-specific guest details, identity-confirmed arrival registration, staff check-in/out history, room readiness checks and housekeeping handoff.
- Added embedded-logo HTML payment receipts and pending room emails. Customer and selected staff payment alerts use persisted queue jobs with retry handling. Expo HTTP success alone is no longer treated as an accepted push ticket.
- Removed the simulated rotating door PIN and fake unlock-success action. The home card now reflects an actual checked-in stay and routes to My Stays.
- Replaced hardcoded featured product IDs/prices and fake consumable stock levels with backend records. Inventory API access is restricted to staff roles.
- Restored anonymous catalog browsing for the existing Continue as guest flow, while keeping bookings, orders, profiles and payment actions authenticated. Room housekeeping notes stay out of API output; unavailable room details cannot be fetched directly.
- Added the payment return screen and strict server-side callback URL allowlisting. Callback status never confirms a payment; backend verification remains authoritative.
- Fixed local notification failures interrupting completed checkout UI, and browser image uploads using native-only FormData objects.
- New boutique orders do not reduce stock before payment. Verified settlement locks the product and deducts once; shortages after collection enter reconciliation and notify finance. Existing orders retain their previous inventory accounting through a migration marker.
- Fixed reused provider IDs causing a unique-constraint error when recording an exception. Existing transaction evidence is preserved, and replayed review cases cannot silently become fulfilled after stock changes.
- Restricted direct bank transfer to rooms, which have an independently reviewed finance approval flow. Dining, boutique and concierge retain Flutterwave checkout, including the provider's bank-transfer channel.
- Added the missing customer dining-history endpoint and combined dining/concierge history in the app. Histories remain scoped to the authenticated user.
- Rejected booking inactive services and unavailable menu items; availability failures produce validation responses instead of exposing server exception text.
- Fixed optional boutique checkout fields, product category persistence, paid-order status options and required incident defaults.
- Protected paid room dates/guest assignments and paid commerce item/pricing fields. Financial records cannot be deleted through owner portal actions; unpaid admin-created commerce records remain pending.
- Reassigned shared-device push tokens to the current account, removed tokens on logout and validated token format. Incorrect profile PINs return validation errors instead of expiring the app session.
- Replaced the nonfunctional notification switch with device-settings access, refreshed home/service histories on focus and separated product/room/service favorite IDs so same-numbered records cannot collide.
- Added production HTTPS API validation, matching native URL scheme, environment examples and build/test CI.

## Deployment requirements

Verification before push: **119 backend tests and 570 assertions passed**; app TypeScript checks, backend asset compilation and Composer platform checks passed. The Expo web export is also checked with a non-deployment sample API URL. No live-system acceptance is implied by these checks.

1. Deploy backend and frontend together from their reviewed commits. The client now uses `/inventory`, `/food-orders` and scoped payment-method requests.
2. Back up database/uploads. Apply the two 29 October migrations for guest registration/staff audit fields and boutique stock accounting.
3. Existing orders are marked as already deducted because the previous checkout API reduced stock at order creation. Reconcile any manually created or abandoned historical orders with actual stock before launch; the migration does not invent stock credits.
4. Set the real HTTPS `APP_URL`, production database, `APP_ENV=production`, `APP_DEBUG=false`, SMTP settings and Flutterwave credentials. Generate a new application key only for a fresh installation; preserve the existing key on upgrades.
5. Build the app with the real `EXPO_PUBLIC_API_URL`. The sample CI API address is deliberately unreachable and is not a deployment configuration.
6. For browser checkout set `FLW_WEB_RETURN_URL` to the storefront's exact public HTTPS `/payment-complete` URL. Configure static hosting to serve Expo's exported detail routes and callback page. Rebuild native apps for the `insignia` scheme/plugin changes.
7. Run and monitor the database queue worker for receipts/push. Configure Expo/EAS project/application IDs and Android/iOS push credentials for native delivery.
8. Complete staging acceptance on MySQL, including concurrent room/product payments, SMTP delivery, physical-device push and payment callbacks, then a controlled live charge/settlement check.

The local MySQL connection was unavailable during this work. Automated database tests use isolated SQLite; no live migrations, real payment charges, SMTP messages or device pushes were performed. Native door hardware remains unintegrated. This Git push publishes source changes, not a production deployment.
