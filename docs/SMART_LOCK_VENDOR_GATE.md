# Insignia Smart Lock — Hardware/API Acceptance Gate

**Status:** Hardware/vendor integration NOT verified. Do not enable automatic guest PIN issuance or claim production unlock capability.

## Required evidence from the vendor

- Exact lock and gateway model numbers, firmware versions, supported connectivity modes and product documentation.
- A verifiable official developer program or API account for the supplied locks, including terms, pricing, quota and credential lifecycle.
- Evidence that the **actual hardware** supports time-bounded guest codes through an API, not only through its companion mobile app.
- Documentation of PIN generation, activation time, expiry, revocation, clock synchronization, fallback/offline behavior and factory reset recovery.
- Confirmation of network/gateway dependency and what happens if the property's power or internet fails.
- Security details: separate property tenant credentials, minimal permission scopes, audit logs, credential rotation and emergency/manager override.
- Vendor contact or support arrangement for actual physical device deployment.

## Integration rules

1. Only issue a temporary access credential for a **paid, confirmed, date-valid** reservation.
2. Never derive lock access from a screenshot, client-local status or unverified Flutterwave callback.
3. Keep vendor API tokens server-side in managed secrets. Do not embed them in Expo/React Native.
4. Store encrypted access-code data with room, lock, booking, validity dates, issuance status, failure reason and audit history.
5. Make issuance idempotent, revoke on cancellation or checkout, and require staff escalation on failures.
6. Never present a dummy PIN as capable of unlocking a real door.
7. Restrict lock administration to authorized IPL/MCL operations roles and log every issuance/revocation action.
8. Provide a supervised manual check-in fallback until vendor API integration passes hardware testing.

## Acceptance tests

- [ ] Vendor proves actual lock can accept API-created guest credential and unlock on-site.
- [ ] Future activation, expiry and revocation are proven at device level.
- [ ] Delayed payment, failed payment, duplicate webhook, cancellation and conflicting booking do not expose valid access.
- [ ] Multiple rooms and bookings remain isolated.
- [ ] Disconnected gateway, depleted batteries and wrong device clock are handled safely.
- [ ] Full access event trail visible to authorized operations staff.
- [ ] Production credentials are issued under the client's own account.

Until all items pass, Jira KAN-14 stays open and the smart-lock experience is a **nonfunctional prototype or planned integration**, not a shipped feature.
