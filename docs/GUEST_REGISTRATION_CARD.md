# Guest registration card — 21 November 2026

## Staff flow

Guest Relations → Guest Arrivals & Departures → Print Card, or open Guest details → Print Registration Card. The protected page opens separately with Print / Save as PDF. It is readable on phones and tablets and prints on A4 portrait. The normal synthetic example has been rendered and inspected as a single page. Longer guest details may flow onto additional pages rather than being clipped.

The card pre-fills contact details, room, dates, agreed average nightly price, booking total, verified payments and remaining balance. Pending/rejected proofs never count as paid. The current room catalog price is not substituted for the agreed booking price. Existing saved registration supplies the arriving guest details, additional guests and recorded identity-check status. Additional names can be entered during check-in or handwritten on the paper card before registration is completed.

Guest and date signature lines, reception identity check and staff initials are provided for paper completion. This is not an electronic signature or an automatic ID check. Printing does not check in a guest, verify payments, release keys or issue a refund. Access is limited to owner/admin, manager and Guest Relations; cleaner, auxiliary and guest accounts are denied. The response uses private/no-store and no-index headers and no third-party page assets.

## Confirmed policy and existing bookings

New reservation snapshots are version 2: check-in 1 PM, checkout noon, 10% administration fee on the eligible refund after used nights and approved charges, no additional VAT deduction, and a 24-hour post-checkout processing commitment for eligible refunds. New booking search/confirmation wording and owner settings display match these terms. Existing saved terms, prices and cancellation deadlines are retained, including the old 2 PM time on old records; no historical terms are rewritten.

The card follows the supplied registration document for late checkout (after noon through 5 PM: half the agreed nightly rate; after 5 PM: a full night), smoking (NGN 250,000), lost/unreturned key (NGN 50,000), registered overnight guests, event approval and assessed damage charges. These are policy disclosures, not automated deductions.

Refundable accommodation balance is left for assessment instead of inventing an eligible refund from unused dates. The deposit refers to prepaid accommodation, not a separate security deposit. Example: verified payment of NGN 200,000 less one used NGN 100,000 night gives NGN 100,000 eligible before a NGN 10,000 administration fee. If only NGN 100,000 was paid and one NGN 100,000 night used, there is no refund just because two nights were originally reserved.

## Deployment

Validation: full backend suite 211 tests / 1,371 assertions passed; app TypeScript and eight regression tests passed; backend Vite and app Expo exports passed. The synthetic card was inspected at phone widths (including a 390-pixel content width) and rendered as one A4 page with signature and footer intact. The public app ZIP checksum and required exported routes were verified.

Deploy backend `feature/guest-booking`, clear application and Filament component caches, refresh config/views and restart existing workers. Deploy the matching app release ZIP for the corrected check-in labels and version-aware cancellation copy. No new migration or seeder is needed for this phase; the additional guest names use the existing registration JSON. The existing database queue/SMTP setup remains required.

## Remaining meeting work — not implemented by this card release

- Guest Relations stay-change requests with manager approval, reasons and audited effective dates/charges.
- Availability-aware late-checkout approval and billing.
- Refund eligibility calculation, approval, 10% deduction posting, payout evidence and reconciliation. Existing manual refund boundaries remain in force.
- Staff-managed City Ledger with verified credits, departmental drawdowns, guest read-only visibility and no double-counting of funds.
- Named cleaner/staff accounts and actual hosting email mailboxes; application logins are separate from mailbox provisioning.

The original PDF has not been modified. Generated preview files contain synthetic data only and are not part of the deployment package.
