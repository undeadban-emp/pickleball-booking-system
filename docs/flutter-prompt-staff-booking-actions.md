# Flutter Update Prompt — Staff Can Approve/Reject/Cancel Bookings

Paste this into your existing Kitchen Line Flutter project conversation. It covers **one backend change only**: staff accounts can now do everything admin could already do in the booking module. Nothing else changed — courts, settings, payment methods, and hero images are still admin-only.

## What changed on the backend

No new endpoints, no request/response shape changes. The only change is **who is allowed to call three existing endpoints**:

| Method | Path | Before | Now |
|---|---|---|---|
| POST | `/admin/bookings/{booking}/approve` | admin only | admin **and staff** |
| POST | `/admin/bookings/{booking}/reject` | admin only | admin **and staff** |
| POST | `/admin/bookings/{booking}/cancel` | admin only | admin **and staff** |

Request/response bodies are unchanged:
- `approve` — no body.
- `reject` — body: `{ "reason"?: string }`.
- `cancel` — body: `{ "reason"?: string }`.
- All three return `{ "data": {booking} }` on success, or `{ "message": "..." }` with a 422 on an invalid status transition (e.g. approving something that isn't `pending_payment`).

A staff account calling these now gets a normal `200`/`data` response instead of the `403` it got before.

## What to change in the Flutter app

1. **Remove the role check that hides these actions from staff.** Find wherever the booking-detail screen or booking-list row conditionally shows the Approve / Reject / Cancel buttons only `if (user.role == 'admin')`, and widen it to `if (user.role == 'admin' || user.role == 'staff')`. This is very likely in the same widget/file as the staff bookings queue, since staff already has read access to `/admin/bookings` — you're just unlocking action buttons on a screen staff can already see.

2. **Do not** add these buttons anywhere in the Courts, Settings, Payment Methods, or Hero Images screens (if a staff nav shell exists) — those stay admin-only, unchanged.

3. **No new API client methods needed** — if `approveBooking()`, `rejectBooking()`, `cancelBooking()` repository methods already exist for the admin flow, staff should call the exact same methods. Don't duplicate them.

4. **Error handling stays the same** — a `403` here now genuinely means "not admin or staff" (e.g. a customer token was used by mistake), not "staff isn't allowed." A `422` still means invalid status transition — keep surfacing `message` from the response body as before.

5. **UX detail**: since staff will now see these buttons on the same shared bookings queue admin uses, consider whether the confirm-dialogs for Reject/Cancel ("Reject this booking?") need any staff-specific copy — the backend logs `changed_by` on the booking's status history regardless of role, so it's already clear in the audit trail which staff member acted.
