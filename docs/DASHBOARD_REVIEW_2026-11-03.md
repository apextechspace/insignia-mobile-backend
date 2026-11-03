# Owner and Operations dashboard review — 3 November 2026

## Scope and limits

Reviewed the owner panel (`/admin`) and Operations panel (`/operations`) using owner, MCL, front-desk and ordinary staff roles. Inspected the implemented resource rules against the workspace `DISCUSSION_SUMMARY.md` and current code. No production accounts or records were created or modified by this review. The customer-facing booking app was not redesigned.

The client asked to defer the new permission policy. Therefore the access map below describes current behavior, not a newly approved access policy. Visibility concerns are explicitly separated from repaired workflow/data bugs. A passing test suite cannot guarantee the absence of every bug or establish which business permissions the client wants.

## Repairs included

1. **Property-area cleaning locations:** separate editable areas, location selection, area checklists, assigned-staff completion and supervisor verification, with no effect on bookable rooms. See `PROPERTY_AREA_CLEANING.md`.
2. **Checklist bypass:** ordinary staff could rewrite or remove assigned checklist items while editing task progress. The form now limits them to progress/notes, and the server checks the original checklist membership before saving. Roles remain unchanged.
3. **Inventory overspending:** issuing items did not check the current stock or lock the balance, and balance updates were separate from movement records. Issuance/restocking now use transactions and a locked item row; quantities must be positive integers and insufficient stock is rejected without creating a movement. The room picker uses its explicit room options rather than an unnecessary nested relationship. Inventory visibility remains unchanged.
4. **Expired checkout clutter:** expired pending online holds appeared in Operations reservations and owner Pending Actions. Both now use the same actionable-pending scope; no bookings are deleted, and pending manual transfers remain available for review.
5. **Conflicting room-release button:** Room Status's Mark Ready action wrote `available` directly instead of using supervised turnover verification. It now uses the existing supervisor service. Active checked-in guests prevent room release, including a maintenance-to-available direct edit; flagging maintenance cannot bypass an unfinished checkout checklist. Existing supervisor roles are retained.

## Current panel and feature map

### Owner — `ipl_admin` / `admin`

Owner panel: rooms and rates; suite bookings; manual-transfer verification; Flutterwave reconciliation cases; products/shop orders; food menus, categories and dining orders; services/service bookings; guest registrations; maintenance requests; user accounts and roles; bank settings; master financial statement and accommodation reconciliation report. Dashboard widgets show occupancy, revenue streams, pending reviews and activity trends.

The same owner can open Operations for property areas, task assignment, shifts, room readiness, guest arrivals/departures, stock, incidents, assets, asset handovers and financial oversight.

### MCL manager — `delivery_partner`

Operations panel access only. Current features: room board/status and supervisor room release, paid-guest check-in/out, readiness review, property areas, cleaning/task assignment and verification, duty roster/shift handover, consumable inventory, asset register, asset handover verification, incident handling, shared accommodation ledger, own operational expenses, daily reconciliation, monthly statements and read-only operating float. MCL cannot access the owner panel or its user management, bank configuration, master statement and ancillary financial dashboard.

### Front desk — `guest_relations`

Operations panel access only. Current features include guest arrivals/departures, room board, readiness review, tasks and property areas, inventory, asset register, incidents, asset-handover viewing, own expense logging and daily reconciliation. Staff scheduling edits and owner finance reports are unavailable. Current task rules still permit front desk to assign and verify area tasks; the requested future permissions review may change that.

### Ordinary staff — `staff`

Operations panel access only. Tasks and duty rosters are scoped to the logged-in worker. Staff can update their assigned checklist progress/notes, mark completed duties, clock in and hand over their own shifts. They cannot create property areas, assign tasks, verify area duties, release guest rooms, enter owner administration or open finance resources (accommodation ledger, expenses, float, daily reconciliation, monthly statements).

The room-status board, consumable inventory, incidents and asset register are currently broadly available. See the unresolved access concerns below before treating this as the client's final permission policy.

## Permissions and consistency concerns held for the client review

These were not silently redesigned while implementing cleaning areas:

- **Asset register:** no role-specific resource create/edit restriction currently prevents ordinary Operations staff from managing asset records. Confirm whether staff should only report damage/condition rather than create/edit assets.
- **Inventory direct edit:** the staff Edit button is hidden, but the resource has no corresponding explicit edit rule/policy. Confirm and enforce the intended server-side edit boundary during the permission pass; hiding buttons alone is insufficient.
- **Incident resolution:** all Operations roles currently share incident lists and resolution actions. Confirm which roles may close incidents and which financial/repair details should be visible.
- **Guest names on room board:** the Operations home widget hides guest details for ordinary staff, while the Room Status table still includes the current guest name. Decide the intended staff visibility and apply it consistently.
- **Monthly-statement ownership:** `MonthlyStatementPolicy` limits MCL updates to their own drafts; the resource edit rule allows draft/queried records without the same submitter check. Resolve that discrepancy before adding multiple manager accounts. Do not assume resource overrides and model policies enforce identical rules.
- **Area/task assignment:** current task assigners include front desk as well as owner/MCL. The new area list mirrors that rule, as requested, instead of introducing an unapproved role scheme.

### Additional configuration finding

The Operations banner labels its clock WAT, but `config/app.php` currently uses UTC and the banner formats `now()` without converting it. The display is therefore one hour behind WAT unless the deployment overrides the timezone. Daily date boundaries should be reviewed together with reporting and stored timestamps before changing the application timezone. No global timezone change or historical timestamp conversion was made in this cleaning-location update.

## Similar-looking sections and their purpose

| Sections | Distinct purpose |
|---|---|
| Room Status / Room Readiness / Guest Arrivals | Physical room state / supervisor cleaning sign-off / actual guest check-in and departure |
| Inventory / Asset Register / Asset Handover | Consumable stock / durable equipment / signed baseline of equipment handed over to MCL |
| Expenses / Operating Float | Bills and approval / money advanced and its running balance |
| Daily Reconciliation / Monthly Statement | Daily cash/POS/transfer reconciliation / MCL's monthly submission for owner review |
| Accommodation Ledger / Owner Master Statement | Shared room revenue / owner-only consolidated financial reporting |
| Owner Bookings / Operations Guest Arrivals | Commercial reservation/payment administration / the physical stay lifecycle |
| Duty Roster / Tasks | Staff shift timing and handover / the actual work, location and cleaning checklist |

These serve different records/workflows. If navigation feels repetitive, simplify the labels and entry points after the permission review rather than deleting their underlying financial or stay records.

## Verification

- Tests exercise the actual MCL task-creation form, staff checklist editing/completion, supervisor verification, inactive-area handling, location conflicts and room-readiness separation.
- Dashboard smoke tests request every registered resource index for owner, MCL, front desk and staff, checking allowed pages render and blocked pages return 403. Owner widgets/reports and the Operations widget are also rendered.
- Data-integrity tests exercise the real inventory issue action, stock movements, expired-hold filtering, room release and maintenance/occupancy safeguards.
- Final full backend regression suite: **165 tests, 900 assertions passed**. The focused final Operations run also passed **25 tests, 96 assertions**, including the newly added room-release safeguards. The ignored local JUnit artifact is `storage/app/dashboard-audit-results.xml`.
- Tests use isolated SQLite databases. They cover model/query/schema compatibility there and the established row-locking order in code; they do not prove simultaneous MySQL transactions or visual layout on every browser. Namecheap/MySQL deployment and hands-on role testing remain necessary.

## Suggested hands-on test order

1. Existing owner: open Operations → Property Areas and verify the six locations.
2. Owner: use Users to create an MCL Facility Manager (`delivery_partner`) account; sign into `/operations/login` in a separate browser session.
3. MCL: assign Reception cleaning to an ordinary staff account; verify the task shows the right location.
4. Staff: confirm only their assigned tasks/shifts appear; tick/save the checklist and Mark Done. Try marking done with an unchecked item first.
5. MCL: Verify Area Duty; confirm the verifier/time and that no room became available.
6. Check out a test stay, complete its room-turnaround checklist, then verify/release the guest room separately.
7. Test inventory issuance at and above available stock, and verify movement/balance consistency.
8. Review the held permission concerns one by one with the client before approving a tighter role matrix.
