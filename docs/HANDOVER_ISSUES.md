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

## Outstanding policy and implementation decisions

- Night-based assisted deposits: first night for 1-6 nights, first three nights for 7+ nights. Existing implementation uses an owner-set percentage. Online Flutterwave remains full payment unless explicitly changed.
- Three guest confirmation email variants aligned with verified deposit/full-payment status.
- Cancellation deadlines, refundable amounts, late-booking grace period, and room-release process: previously paused; no automatic refunds or room release currently approved.
- Optional VAT-inclusive label: not implemented; owner enters final VAT-inclusive prices manually.
- Financial coverage review: the existing master report records accommodation from the paid-booking ledger; verified assisted part-payments before full settlement require a separate reporting check. Printing fixes do not change that recognition logic.

Do not publish refund promises or change active payment rules while only fixing statement printing.
