# Manual external reservations

## Entry and inventory

Guest Relations or the facility manager opens Operations → Guest Arrivals & Departures → **Record External Booking**. Owner/admin may also use it. Cleaner, auxiliary and guest accounts cannot create external reservations.

Check the original confirmed channel record and enter the channel (Booking.com, Airbnb, Expedia or another named channel), external reference, room-line reference, physical room, dates, guest details, this room line's agreed total and original channel payment/cancellation terms. Missing guest phone/email is permitted when the channel has not supplied it. No guest account is created or linked automatically.

One entry represents one physical room. For multiple rooms under one channel reference, use distinct room-line references and the correct per-room amounts, not the whole reservation price repeated for every room. Source/reference/room-line identity is unique in the database. The shared room-row transaction lock checks confirmed stays and active local holds. Adjacent departure/arrival dates remain possible. A conflicting external reservation is rejected locally and must be escalated, never silently substituted for an existing paid stay.

The imported reservation is confirmed and blocks its dates locally without a 15-minute checkout expiry. It does not mark money as paid, change the physical room's readiness, generate a bank receipt or post ledger revenue. Its price and original channel terms are saved instead of inheriting our current direct price, deposit percentage or cancellation/refund policy. Original room/date/price/channel identity remain protected.

## Payment arrangements

- **Guest pays Insignia directly:** Guest Relations can record property payment proof, using existing private assisted receipts and reviewer permissions. Only verified amounts count as receipts, once. Instalments are limited by the actual balance; the direct-assisted requirement to collect the entire balance in one final instalment is retained for direct reservations, not imposed on a different channel agreement. Full verified accommodation payment is still required before check-in.
- **Channel collects:** Recording the booking does not prove the guest has paid. An authorised owner or manager payment reviewer uses **Verify Guest Channel Payment**, uploads private channel evidence (PDF or supported image) and records a verification note. This explicitly confirms full guest accommodation payment for that room line. It permits normal check-in when dates, room readiness and guest registration are valid, without charging the guest again. It does not set the property's `paid_at`, post a cash receipt or claim a bank payout to Insignia.
- **Not confirmed / review required:** Default safe choice. Collection and check-in remain blocked. A manager/owner uses **Confirm Payment Arrangement** against the original channel record; previous/new arrangement, reviewer, date and note are retained. The arrangement cannot be switched after payment evidence, verified guest channel payment or check-in; finance must resolve those cases.

Existing owner-controlled manager-verification permission also governs channel-payment evidence. Guest Relations cannot verify it. Channel evidence remains on the private disk; only authorised reviewers can download it. Guest APIs expose a safe channel/payment status, not internal evidence paths or reviewer notes. Guest-facing messages and the guest's own booking list distinguish a channel-paid stay from an unpaid direct booking; they do not offer a second checkout for the imported reservation.

## Arrival, departure and cancellation

Imported reservations appear in arrivals and the manager's upcoming list, including confirmed externally reserved dates before payment. Registration cards include source/reference and original channel terms. Normal date, readiness, identity and occupied-room checks continue to apply. Checkout starts the normal housekeeping workflow.

Record a cancellation only after confirming it in the original channel. **Record Channel Cancellation** requires a manager/owner, the channel cancellation reference/reason and explicit confirmation. The guest must not already be checked in. Pending property proofs must be verified or rejected first. If property money has been verified, only owner finance can cancel. Cancellation releases the dates locally but preserves the money, proof, reviewer and cancellation history. It does not pay a refund or set the room ready for another guest.

## Manual operation and finance boundaries

There is **no channel API, automatic calendar synchronisation, scrape, automatic repricing or automatic refund**. Staff must update the channel calendar when direct bookings, holds, changes or cancellations affect its available inventory, and promptly record confirmed channel reservations locally. This manual gap still permits cross-platform overbooking; local checks cannot eliminate a reservation race on an external platform. Escalate conflicts rather than moving an existing paid guest.

Original channel modifications/repricing require management review; this first release does not silently rewrite confirmed dates or prices. The planned stay-amendment workflow is separate. Mixed channel/property collection and partial channel-payment guarantees must remain under owner review rather than using the full-channel-payment confirmation incorrectly.

Channel payout batching, commissions, net bank settlements and payout allocation/reconciliation are **not posted by this initial external-booking workflow**. Owner finance must reconcile them separately. A guest channel-payment confirmation is not a property bank receipt. The existing receipt statements only show verified property receipts recorded in the system; do not treat them as a complete account of channel-collected sales or channel bank payouts. No commission is silently deducted from the guest's room total and no fabricated income is created to make the dashboard look paid.

## Deployment and acceptance

Deploy the backend first, clear application/component caches, run `php artisan migrate --force` for `2026_11_21_000001_add_external_booking_details`, refresh views/config and restart existing workers. Deploy the matched storefront ZIP for the guest status labels. No seeder or live data clearing is required. The new migration adds nullable fields and a unique external-unit index; existing direct bookings are preserved.

Test: import a confirmed pay-at-property stay; verify its quote and channel policy; confirm it blocks overlapping direct dates; record/verify property money; check in and out. Then test channel-collected and unknown-payment cases: verify reviewer restrictions and private proof, no duplicate guest charge, no false cash income, and safe manager review. Check multi-room references, duplicate import rejection, cancellation with/without property money, registration print, guest history and notifications. Review actual channel calendars manually throughout.
