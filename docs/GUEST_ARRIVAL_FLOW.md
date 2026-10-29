# Guest registration, check-in and checkout

In the Operations portal, open **Guest Arrivals & Departures**, then select a reservation or **Guest details**. The guest page is specific to a reservation: the person who booked and the arriving guest can be different, and arrival details do not overwrite the account profile.

## Arrival

1. Find the guest by booking number, booking contact or suite. Use **Arrivals today** to narrow the list. Open **Guest details** to review contact details, scheduled dates, payment verification and current room readiness.
2. Select **Check In**. Confirm the arriving guest's full name and phone, the number of adults and children, and optional arrival notes. Physically verify the guest's identity and tick the confirmation.
3. Select **Complete check-in**. The server checks staff authorization, verified payment, active booking status, arrival dates, room readiness and conflicting active occupancy. Registration and identity confirmation are required even when the service is called outside the UI.
4. A successful check-in stores the registration, arrival time and staff member, and marks the suite **occupied**. The details page moves to **In house**. Hand over the physical keys after the success message. This does not provision smart-lock access.

A failed attempt displays a reason and leaves the room, guest registration and arrival history unchanged. Pending payment, a future or expired stay, an unready suite, or an already occupied room must be resolved before retrying. Duplicate check-ins cannot overwrite the original registration or staff history.

## Departure

1. Use **In house** or **Departures due** to find the reservation, then open guest details.
2. Collect the keys and confirm that the guest has departed. Select **Check Out** and confirm.
3. Checkout records the staff member and time, completes the stay, marks the suite **cleaning** and creates a fresh housekeeping checklist in one transaction.
4. Housekeeping completes its checklist. A supervisor verifies readiness before the room can be released for the next guest.

The details page retains guest registration and arrival/departure history after checkout. Legacy arrivals without registration continue to show **Not recorded** for missing information and can still check out.

## Access and deployment

Only owner/admin, IPL finance admin, operations/delivery partner and guest relations roles can open guest details or process arrivals/departures. General staff and guest accounts cannot access the page directly. Registration notes and identity verification are excluded from customer booking API serialization.

Deploy the new migration `2026_10_29_000001_add_guest_registration_to_bookings.php` with the code. It adds nullable registration and staff audit fields, leaving existing stays intact. Run the normal database backup and migration checks before applying it on live. Email/push setup is tracked separately in the launch checklist; this change adds the front desk registration and stay-management flow.

## Verification

The full backend suite passes: **100 tests, 468 assertions**, including page access restrictions, guest registration validation, actual guest versus booking contact, blocked arrivals without mutation, staff audit history and housekeeping creation. Tests used an isolated SQLite database. The configured local MySQL connection at `127.0.0.1:3306` refused connections, so the migration has not been applied to the development database or a live host.
