# Reservation policy and receipt reporting

## Owner activation

After deploying the backend migration and matching frontend build:

1. Admin → Finance → Bank Transfer & Deposit Settings.
2. Enable deposits and choose **First night (1–6 nights); first three nights (7+ nights)**. Retain Percentage mode when the agreed policy is percentage-based.
3. Enable cancellation terms only after reviewing the displayed business policy. The extended-stay late-booking grace period has a separate checkbox; it is not silently assumed.
4. Save deposit policy. Choose whether VRL can verify payments using the existing permission checkbox.
5. Admin → Suites & Rooms → Edit: optionally enable **Show “Includes 7.5% VAT”** only when that final nightly price already includes it. No VAT or discount arithmetic is added.

Existing settings are preserved on migration: percentage mode remains selected, cancellation wording and the optional grace period are initially off. Existing bookings retain their recorded deposit minimum and receive no new cancellation promises. New bookings save their terms, stay dates, and charges; later owner-policy changes do not overwrite these agreements.

For a new room or different stay dates, release the unpaid checkout and create a new reservation rather than overwriting its agreed price and terms. Submitted/approved monthly statement snapshots remain historical records.

## Reservation and email behaviour

- Online Flutterwave bookings require 100% payment. A staff deposit option does not enable online instalments.
- Night-based staff reservations: one night = full payment; 2–6 nights = first night; 7+ nights = first three nights, using the final room price without another VAT charge.
- Receipt upload does not prove payment. Existing owner/delegated VRL verification remains required; Front Desk cannot grant itself verification permission.
- Qualifying verified payment secures the dates. Check-in requires the full balance to be verified.
- Below-minimum verified credits receive a payment acknowledgement without claiming reservation confirmation.
- Verified qualifying deposits receive the short/extended-stay confirmation email; final settlement receives a full-payment email with zero accommodation balance. Each instalment email distinguishes this payment from the total paid.
- Emails contain the embedded Insignia logo, guest/room details, local check-in/check-out times, nights, references, balances, and contact information. An email address is required for delivery; staff reservations can still be phone-only.
- Check-in is 2 PM and check-out is noon, Africa/Lagos. Cancellation cutoffs are calculated from scheduled check-in: 48 hours for 1–6 nights, seven days for 7+ nights. If the optional late-booking grace rule is enabled, eligible extended stays use the earlier of booking + 24 hours or scheduled arrival.
- Late cancellation charges cover the first night or first three nights, capped at verified money received. Confirmation emails show the corresponding remaining refundable amount. Bookings made after their free-cancellation cutoff are explicitly labelled.
- Refunds and no-show room release remain manual management decisions; this release does not execute payouts, cancel stays, or release rooms automatically. The extended-stay noon-next-day point is informational for management review.

## Statements

The IPL master statement and newly generated VRL monthly statements now include verified assisted receipts before full settlement, using **verification time** (Nigeria reporting dates). Proof/transaction dates remain recorded separately. Unverified submissions are excluded. The complete-booking revenue row is excluded when its verified instalments are reported, preventing double counting across periods. Underlying paid-booking ledger entries and previous approved statement snapshots are retained, not rewritten. Management fee calculation remains the existing percentage of positive net accommodation receipts; float movements are not revenue.

## Live acceptance

1. Enable the desired owner settings. Create a three-night staff reservation and verify the minimum equals one final nightly rate; repeat with seven nights and three nightly rates.
2. Upload a payment proof and confirm it does not confirm payment before verification. Verify it using an authorised owner/VRL account.
3. Confirm the dates are secured, the confirmation email shows the correct deposit and balance, and check-in is blocked.
4. Record and verify the balance. Confirm the final email shows zero balance and check-in is available.
5. Export/print the owner statement. Confirm both receipts appear once, with the total equalling the booking value; compare VRL monthly receipts.
6. Test an online booking: Flutterwave still requests the full final price. Confirm its email is the full-payment variant.
7. Inspect short/extended cancellation deadlines and the optional grace rule on a NEW reservation. Old reservations must retain their previous terms.
8. Enable/disable the VAT-inclusive label and confirm it does not change the price.

Keep the existing queue worker/cron running after deployment and restart workers to load the new email template code.

Validation: 54 distinct focused Laravel tests passed across reservation, email, reporting, payment, dashboard, and guest-access suites. Frontend type checking and the production export passed. The three email variants were rendered with embedded branding and reviewed locally; production delivery and bank/gateway settlement remain live acceptance checks.

## Verified receipts and guest updates — 19 November follow-up

- Owner dashboard now uses the financial statement's verified receipt rows for its **Verified Receipts (30d)** card. Verified assisted deposits count immediately; the balance counts when verified. Full settlement does not count the earlier deposit again. Values show kobo precision and use the last thirty Nigeria calendar dates. This is gross recorded receipts, not profit or confirmation of bank settlement.
- Guest details distinguish **Part payment verified · Balance outstanding** from **Paid in full · Verified**. The method summary uses verified assisted payment methods instead of displaying a generic bank-transfer value for a verified POS payment. The timestamp is explicitly labelled **Full payment verified at**.
- Assisted reservations with a valid contact/linked-account email receive branded messages when the room hold is created, proof is submitted, a payment is verified, and check-in/check-out completes. Hold/proof acknowledgements are explicitly not verified payment receipts. Verified-payment emails retain current receipt, cumulative paid and balance amounts. All use the existing database queue/SMTP setup; the queue worker or cron must continue running.
- Guest details includes **Send Guest Update via WhatsApp** for a valid guest phone number. It opens a draft with the current booking status, dates, verified amount, pending-proof amount if applicable and balance. Staff review and press Send in WhatsApp. No WhatsApp API or automatic send is implied. Nigerian `0…` phone numbers are converted to `234…`; email is optional. The button can also be used when an email exists.
- The manager dashboard now displays this month's verified accommodation receipts. Operations → Finance → **Accommodation Receipts** provides custom dates, this/last month and this/last year shortcuts, a monthly chart, CSV and standalone Print/PDF. Only owner/VRL manager roles can access the page/export/print; the department is fixed to accommodation on the server. Kitchen, shop, concierge, unverified proofs, operating float and expenses are excluded. This is receipts before operating costs, not profit or projected future income. Monthly management statements remain a separate workflow.
- Payment control: only one unreviewed proof can be submitted for a reservation at a time. Once the qualifying deposit secures the booking, the next collection must equal the full remaining balance. References already submitted/credited elsewhere are rejected; the global verified-reference constraint remains the final concurrency safeguard. A fully paid stay accepts no further collection. Existing unverified extra proofs are labelled **Not credited · Reservation already paid in full** and never count as income.
- **Reject Incorrect Proof** is available only to an authorised payment reviewer. A reason is mandatory; reviewer, timestamp and rejected evidence are preserved. It cannot reject or reverse a verified payment. Guest Relations cannot approve/reject proofs. Owner-controlled verification permissions and below-minimum deposit exceptions remain in place. Rejecting proof does not refund money or extend the room hold.
- Deploy backend only: pull `feature/guest-booking`, `php artisan optimize:clear`, `php artisan filament:clear-cached-components`, **`php artisan migrate --force`**, verify/extract the updated portal asset ZIP, then `php artisan config:cache`, `php artisan view:cache`, `php artisan queue:restart`. Migration `2026_11_19_000002_add_assisted_proof_review_outcomes` adds nullable audit fields without deleting or changing historical receipts. No seeder, app build or data reset is needed. Restart only signals existing workers; it does not start a worker or replace cPanel cron.
- Existing verified records are included automatically in the recalculated dashboard totals; no financial backfill is needed. New lifecycle messages apply to future actions. Historical emails are not replayed automatically.
- Follow-up verification: complete backend suite **208 passed, 1,344 assertions**; final guest-message check **6 passed, 37 assertions**, including zero balance on legacy fully paid records. Portal Vite build and ZIP checksum passed. Manager report/print layout and guest acknowledgement layout were rendered with synthetic data and inspected locally. Live email delivery and cPanel queue execution still need the normal deployment smoke test.
