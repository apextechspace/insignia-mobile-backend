# Auxiliary handover

Auxiliary uses the existing database, verified payment records, and customer receipts. It does not introduce another payment gateway or move money between bank accounts.

## Access

- IPL/admin: existing owner controls and access to Auxiliary.
- Auxiliary Staff: `/auxiliary/login`; verified kitchen, shop, and service orders, fulfilment actions, and a read-only menu. No room bookings, financial approvals, bank settings, or price editing.
- VRL, Guest Relations, and cleaners: no Auxiliary access. Commerce payment notifications are excluded, including historical notifications and queued pushes.

Create the account under Admin → Users, choosing Auxiliary Staff. No seed or default password is needed.

## Fulfilment

- Kitchen: paid → preparing → delivered.
- Shop: confirmed → processing → shipped → delivered.
- Services/coworking: confirmed → in-progress → completed.

Only records with verified `paid_at` appear. Each transition rechecks payment and current status under a database row lock, and records the actor and transition in `auxiliary_fulfilment_events`. Payment values cannot be edited here. Coworking and errands use the existing service bookings; no new capacity calendar is introduced.

## Financial boundaries

The owner retains all commerce records and the master statement. Operations reconciliation and CSV exports exclude legacy records containing ancillary cash. New Operations reconciliation records cannot record ancillary cash.

New Operations expenses are accommodation expenses. Existing expenses remain unclassified after migration: IPL must classify these under Operations → Expenses before they are included in newly generated accommodation statements. Auxiliary expenses and unclassified expenses are excluded from those statements. Previously submitted statement snapshots remain historical records and are not recalculated by this deployment.

## Deployment and live acceptance

Pull `feature/guest-booking`, migrate, extract the checked portal asset ZIP, clear cached Filament components, rebuild configuration/views, and restart queue workers. Keep the existing queue worker/cron running.

Test an Auxiliary login, a paid order in each queue, fulfilment progression, and guest/owner notifications. Confirm VRL and Front Desk cannot open `/auxiliary` and cannot see commerce notifications. Confirm unpaid orders stay hidden and owner statements still include all business streams. Review historical expense classification with IPL before approving a new accommodation statement.
