# Cleaning duties for rooms and property areas

Property-area cleaning uses the existing Operations Tasks workflow. No changes were made to the staff role list or panel access rules.

## Initial locations

The migration adds Upstairs Foyer, Reception, Downstairs Foyer, Inverter Room, Outdoor and Boys’ Quarters to a separate `property_areas` table. They are not guest rooms and never appear in room search, pricing, reservations or room occupancy counts. Names and instructions are editable.

## Assigning a duty

1. Open `/operations` with an existing owner or MCL account.
2. In **Property Areas**, review the locations. Add another area or mark a retired area inactive. Areas with task history cannot be deleted.
3. Open **Tasks → New Task**. Set Location Type to **Property Area**, choose the area and assign the worker.
4. Daily Cleaning / Refresh is selected for an area. Selecting an area supplies an editable starter checklist when the task has no checklist yet. Other ordinary duties can still use Guest Room or General Task locations.
5. The assigned worker opens the task, ticks the assigned checklist items, saves, then selects **Mark Done**. Unchecked items prevent completion. Staff cannot remove/rewrite their assigned checklist to bypass it.
6. A current supervising role uses **Verify Area Duty** after inspection. The task records the verifier and timestamp. This action never changes a guest room's readiness.

The same current task assignment roles apply: owner/system admin, MCL and front desk can assign; ordinary staff see their assigned tasks. This preserves existing task access, including front desk assignment capability, pending the separate permissions review requested by the client.

## Integrity rules

- A task cannot refer to both a room and a property area.
- Room Turnaround cannot target a property area.
- Inactive areas cannot receive new assignments, but existing duties remain visible.
- Historical room tasks and automatic checkout-turnaround task creation are preserved.
- Verification of an area requires a completed checklist; ordinary staff cannot verify it.

## Deployment

This is a backend-only change. After pulling the branch, run:

```bash
php artisan migrate --force
php artisan filament:clear-cached-components
php artisan config:cache
php artisan view:cache
php artisan queue:restart
```

No seeder is needed. The migration adds only these six property-area defaults; it does not recreate rooms, accounts, demo bookings or financial records. No frontend ZIP or new dashboard assets are required.
