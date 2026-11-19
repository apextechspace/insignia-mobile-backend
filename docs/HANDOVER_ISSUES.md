# Handover issues - 19 November 2026

Meeting: 3:30 PM, Africa/Lagos, 19 November 2026.

## Statement printing - fixed and tested; live verification pending

- Fixed Master Statement Print/PDF producing the portal sidebar instead of the statement. A standalone, owner-only print route preserves selected dates and department, summary totals, and transaction details.
- Rendered the supplied two-entry CSV and a separate 120-row synthetic pagination fixture. Verified all 120 references and repeated table headings across ten pages. Existing report calculation and CSV data format are unchanged.
- Deploy backend, clear route cache, refresh compiled views. Open Master Financial Statement → select filters → Print / PDF → Print / Save as PDF. Verify the live figures against the CSV export.
- Nine statement/access tests passed; CSV amount and reference were also checked against the print response. No new migration or frontend build is required for this printing fix.

## Completed and pushed

- Deposit percentage input visibility in dark mode.
- Final nightly price and optional discount offer badge, without automatic VAT or deductions.
- Booking card capitalisation, Night/Nights, bracketed stay length, Select Room / Select Suite.
- Auxiliary order handling and role separation (live acceptance remains necessary).

## Follow-up release - implemented; activation and live acceptance required

- Owner-selectable night-based assisted deposits, alongside the existing percentage option. Online Flutterwave remains full payment.
- Short/extended-stay deposit confirmations and full-payment emails, only after verification, with current payment and total-paid/balance figures.
- Saved cancellation terms, local deadlines, capped cancellation charges and refundable amounts. Optional late-booking grace period is separately controlled. Defaults do not retroactively apply a policy to existing reservations.
- Owner-controlled VAT-inclusive label, without tax calculations or price changes.
- Verified assisted receipts included once in both IPL and newly generated VRL statements, including cross-month deposits and balances. Previously approved snapshots remain historical records.
- Automatic refund payouts and automatic room release remain outside the authorised scope; management handles those manually.

See RESERVATION_POLICY_HANDOVER.md for exact owner settings, scope, and the live test checklist. Production SMTP, queue delivery, and actual gateway/bank payments still require the live acceptance steps.
