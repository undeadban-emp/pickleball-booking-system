# Flutter Build Prompt — Kitchen Line

Paste everything below into a fresh conversation with a coding agent (or use it yourself) to scaffold the Flutter client for the Kitchen Line pickleball booking system. It targets the Laravel API already implemented in this repo at `routes/api.php`.

---

## 1. What you're building

A single Flutter app (Android + iOS) with **three role-driven experiences** behind one login screen:

- **Customer** — browse courts, check availability, book a slot, pay via GCash, view/track their bookings, show a QR check-in code.
- **Staff** — full Bookings queue (approve/reject/cancel), Check-in scanner, and Day Schedule (a day-at-a-glance calendar of every booking) — the same three operational screens staff get on the web admin panel.
- **Admin** — everything staff can do, plus a Dashboard (sales/queue overview), Reports (Booking / Revenue & Finance / Client), Settings (General, Time-of-day Groups, Court Rates, Location), court management, payment methods, and homepage hero images. Courts/payment-methods/hero-images are unchanged from before; Dashboard, Day Schedule, and Reports/Settings are new in this pass.

The user's `role` field (`customer` | `staff` | `admin`) returned at login determines which navigation shell and screens are shown. Don't build three separate apps — one app, role-gated routing (e.g. a `role`-based `GoRouter` redirect or a switch on the shell widget).

## 2. Networking contract

**Base URL:** configurable per build flavor (dev/staging/prod), e.g. `https://kitchenline.example.com/api`.

**Every single request** (including public/browsing endpoints) must send this header, or the API returns `403`:

```
X-Jocos-Token: pickleball-8f3k2m9x4q7w1p6n
```

This proves "this is the official app build," not "this is a trusted user" — it's the same value for every install. Store it as a build-time constant (e.g. `--dart-define=APP_TOKEN=...` or an `.env` loaded via `flutter_dotenv`), not hardcoded in a widget, so it can be rotated without a full rebuild of business logic. Treat it as low-value secrecy: anyone who decompiles the APK can extract it. It is not a substitute for the per-user auth token below.

**Authenticated requests** additionally send:

```
Authorization: Bearer <token from /auth/login or /auth/register>
```

Tokens are Laravel Sanctum personal access tokens — they don't expire automatically, so persist them in `flutter_secure_storage` and attach them to every request via an HTTP client interceptor (Dio `Interceptor` recommended). On any `401`, clear the stored token and route back to the login screen.

Suggested stack: `dio` (with a base interceptor for the two headers above + auth token + error unwrapping), `riverpod` for state, `go_router` for role-based routing, `flutter_secure_storage` for the token, `google_fonts` for typography, `cached_network_image` for court/hero images, `firebase_messaging` for push (see §5).

All list endpoints return `{"data": [...]}`; single-resource endpoints return `{"data": {...}}`; paginated endpoints (`/admin/bookings`, `/bookings/mine`) return Laravel's standard paginator shape (`data`, `current_page`, `last_page`, `total`, etc.). Error responses are `{"message": "..."}` with 4xx/5xx status codes — surface `message` directly in a snackbar/toast.

## 3. Auth flows

### Login — `POST /auth/login`
Body: `{ "email": "...", "password": "...", "device_name": "iPhone 15" }` → `{ "data": {user}, "token": "..." }`.

Staff/admin accounts have a second option: submit `code` instead of `password` (a daily rotating fallback PIN meant for a shared front-desk device, not for normal use). Only expose this as a "Staff device login" toggle on the login screen, hidden by default, and never offer it to customer accounts — the backend rejects it for the `customer` role anyway.

### Register (customer only) — `POST /auth/register`
Body: `{ "name", "email", "phone"?, "password", "password_confirmation", "device_name"? }` → same shape as login. Password policy: min 8 chars, mixed case, a number, a symbol — validate client-side too so users don't round-trip on a 422.

### Current user — `GET /auth/me` (auth required) → `{ "data": {user} }`. Call on app start if a token is stored, to confirm it's still valid and refresh the cached profile/role.

### Logout — `POST /auth/logout` (auth required) — revokes the current token server-side; clear local storage regardless of response.

### Register device for push — `POST /auth/device-token` (auth required)
Body: `{ "fcm_token", "device_name"? }`. Call after login and whenever Firebase rotates the token.

## 4. Endpoint reference by role

### Public (no auth token needed, still needs the app header)
| Method | Path | Purpose |
|---|---|---|
| GET | `/courts` | Active courts list (id, name, description, location, default_price) |
| GET | `/courts/{court}/slots?date=YYYY-MM-DD` | Available slots for one court on a date |
| GET | `/availability?date=YYYY-MM-DD` | All courts + their slots for a date, with `available` / `pending` / `booked` / `blocked` status per slot — use this for a calendar/grid view |
| GET | `/app/latest-version` | `{ "data": { version, version_code, release_notes, file_size, download_url } }` or `{ "data": null }` — compare `version_code` against the installed build number to prompt an in-app update (Android sideload flow, since this isn't a Play Store release) |

### Customer (`role: customer`)
| Method | Path | Purpose |
|---|---|---|
| GET | `/bookings/mine` | Paginated list of the logged-in user's bookings — each item is the summary shape below (no `payment`/`history`) |
| POST | `/bookings` | Create a booking. Body: `{ "court_id": 1, "court_slot_ids": [12, 13] }` (1–6 contiguous slots). Returns the raw booking plus `payment_info` (GCash number/QR) |
| GET | `/bookings/{booking}` | Booking detail — 403s if it's not the caller's booking |
| POST | `/bookings/{booking}/gcash-reference` | Submit GCash payment reference after transferring. Body: `{ "gcash_reference": "..." }`. Returns the summary shape + `payment` |
| GET | `/bookings/{booking}/checkin-qr` | Returns `{ checkin_token, checkin_url, expires_at }` once the booking is `confirmed` — render `checkin_token` as a QR code (e.g. `qr_flutter`) for the customer to show at the front desk |

All of `/bookings/mine`, `/bookings/{booking}`, and `/bookings/{booking}/gcash-reference` return the same **display-ready** fields — already formatted server-side (12-hour AM/PM times, human status labels), don't reformat them client-side:

```json
{
  "id": 2,
  "reference": "PB-20260723-MLHQ",
  "status": "confirmed",
  "status_label": "Confirmed",
  "customer": "Demo Player",
  "phone": null,
  "email": "player@kitchenline.app",
  "court": "Court A, the show court",
  "schedule": [
    "Jul 28, 2026, 1:00 PM to 2:00 PM",
    "Jul 28, 2026, 2:00 PM to 3:00 PM",
    "Jul 28, 2026, 3:00 PM to 4:00 PM"
  ],
  "total": "900.00"
}
```
`id` is the booking's numeric ID — use it to build the detail/action routes (`/bookings/{id}`, `/bookings/{id}/checkin-qr`, and the staff approve/reject/cancel routes below), don't parse it out of `reference`. `phone`/`email` are `null` when not on file — show a placeholder ("No phone" / "No email provided"), same convention as the check-in summary in §4's staff section.

`GET /bookings/{booking}` (detail only) adds two more keys on top of the shape above:
```json
{
  "payment": {
    "reference": "312321",
    "submitted_at": "Jul 23, 8:20 AM",
    "proof_url": "https://.../storage/payment-proofs/xyz.jpg"
  },
  "history": [
    { "status": "confirmed", "label": "Confirmed", "at": "Jul 23, 4:26 PM", "by": "Kitchen Line Admin" },
    { "status": "pending_payment", "label": "Pending Payment", "at": "Jul 23, 4:20 PM", "by": "Demo Player" }
  ]
}
```
`payment` is `null` when the booking never went through the GCash flow (e.g. booked at the front desk) — hide the Payment section entirely rather than rendering it empty. `history` is newest-first; render each entry as `"{label} {at} by {by}"`.

Booking detail screen layout (top to bottom): reference + status badge → **Customer** (name, phone, email) → **Court & time** (court name, one line per `schedule` entry, total) → **Payment** (reference, "Submitted {submitted_at}", proof image — omit section if `payment` is `null`) → **History** (status timeline, newest first).

Customer booking flow screens: Court list → date/slot picker (use `/availability` for the grid) → review & confirm → payment instructions (GCash QR + reference field) → booking status (pending/confirmed) → QR check-in code once confirmed.

### Staff (`role: staff`, also usable by admin)
| Method | Path | Purpose |
|---|---|---|
| GET | `/admin/bookings?status=&court_id=` | Paginated booking list, filterable by `status` and `court_id` — each item is the same display-ready summary shape as §4's customer section (with `id`), so the queue can render straight off it without a separate detail call |
| GET | `/admin/bookings/latest?last_id=` | Polling endpoint for new bookings since `last_id` — use for a live front-desk board. Still returns the raw booking model (not the summary shape) since it's a change-detection feed, not something rendered directly |
| POST | `/admin/bookings/{booking}/approve` | Approve a pending-payment booking — staff and admin both |
| POST | `/admin/bookings/{booking}/reject` | Body: `{ "reason"? }` — staff and admin both |
| POST | `/admin/bookings/{booking}/cancel` | Body: `{ "reason"? }` — staff and admin both |
| GET | `/admin/schedule?date=YYYY-MM-DD` | Day Schedule: every booking touching that date (default today), sorted by start time — see shape below |
| POST | `/checkin/validate` | Body: `{ "token": "..." }` — the entire check-in: verifies the scanned/typed code belongs to a live, confirmed booking and returns a display-ready summary. Read-only, no separate confirm step — this call doesn't change the booking's status. |

`/admin/schedule` response — one entry per booking on that date, each the same summary shape as above (`id`, `reference`, `status`, `status_label`, `customer`, `phone`, `email`, `court`, `schedule`, `total`) plus these extra keys:
```json
{
  "data": {
    "date": "2026-07-28",
    "bookings": [
      {
        "id": 2,
        "reference": "PB-20260723-MLHQ",
        "status": "confirmed",
        "status_label": "Confirmed",
        "customer": "Demo Player",
        "phone": null,
        "email": "player@kitchenline.app",
        "court": "Court A, the show court",
        "schedule": ["Jul 28, 2026, 1:00 PM to 2:00 PM", "Jul 28, 2026, 2:00 PM to 3:00 PM", "Jul 28, 2026, 3:00 PM to 4:00 PM"],
        "total": "900.00",
        "is_guest": false,
        "payment": { "reference": "312321", "submitted_at": "Jul 23, 8:20 AM", "proof_url": "https://.../payment-proofs/xyz.jpg" },
        "note": null,
        "rebooked_from": null,
        "history": [
          { "status": "confirmed", "label": "Confirmed", "at": "Jul 23, 4:26 PM", "by": "Kitchen Line Admin" },
          { "status": "pending_payment", "label": "Pending Payment", "at": "Jul 23, 4:20 PM", "by": "Demo Player" }
        ]
      }
    ]
  }
}
```
`payment` is `null` when the booking never went through GCash (same convention as the customer detail screen — hide the section rather than render it empty). `note` is a human-readable reason shown only for rejected/cancelled bookings (e.g. `"Cancelled by Kitchen Line Admin — Rained out"`), otherwise `null`. `rebooked_from` is non-null when this booking replaced an earlier rained-out/rescheduled one — show "Rebooked from {reference} (originally {schedule[0]})".

Day Schedule screen: a month calendar (any date-picker widget works — the API doesn't need a full calendar grid, just pass the tapped date) above a list of that day's bookings, tap-through to a detail sheet using the fields above. This is the same screen and same data as the web "Day Schedule" page, just native.

`/checkin/validate` response shape (already formatted server-side — don't reformat dates/prices client-side, just render the strings as-is):
```json
{
  "data": {
    "reference": "PB-20260723-MLHQ",
    "court": "Court A, the show court",
    "schedule": [
      "Jul 28, 2026, 1:00 PM to 2:00 PM",
      "Jul 28, 2026, 2:00 PM to 3:00 PM",
      "Jul 28, 2026, 3:00 PM to 4:00 PM"
    ],
    "customer": "Demo Player",
    "email": "player@kitchenline.app",
    "total": "900.00"
  }
}
```
`schedule` is one line per booked slot (a booking can span several contiguous hours, so it's a list, not a single string) — render each as its own row. `email` can be `null` for a guest booking with no email on file — show a placeholder ("No email provided") rather than blank. `total` is a plain decimal string; prefix it with `₱` client-side same as everywhere else in the app.

Staff screens: Bookings queue (with status filter chips + approve/reject/cancel actions), Day Schedule (calendar + day list, see above), Check-in scanner (camera QR scan via `mobile_scanner` → auto-fill `/checkin/validate`). On success, show a modal/dialog prompt titled **"Booking summary"** listing, in order: Reference, Court, Schedule (one line per slot), Customer, Email, Total — this dialog *is* the check-in, there's no confirm button. Dismissing it returns to the scanner ready for the next code. On failure (404/410), show the error `message` from the response (invalid code / expired code) instead of the summary dialog.

### Admin (`role: admin`) — everything staff has, plus a dashboard, reports, and full catalog/settings management:

**Dashboard** — `GET /admin/dashboard?from=&to=` (the optional range is the same "sales for a date range" card as the web dashboard; omit both to skip it):
```json
{
  "data": {
    "pending_count": 2,
    "confirmed_today": 3,
    "checked_in_today": 1,
    "courts_under_maintenance": 0,
    "sales_today": { "total": "2100.00", "count": 3 },
    "sales_yesterday": { "total": "0.00", "count": 0 },
    "sales_range": { "from": "2026-07-01", "to": "2026-07-23", "total": "9400.00", "count": 12 },
    "needs_review": [
      { "id": 4, "reference": "PB-...", "status": "pending_payment", "status_label": "Pending Payment", "customer": "...", "phone": null, "email": "...", "court": "...", "schedule": ["..."], "total": "300.00", "gcash_reference": "554321", "proof_url": "https://.../payment-proofs/abc.jpg" }
    ]
  }
}
```
`sales_range` is `null` until both `from`/`to` are supplied. `needs_review` is the same summary shape used everywhere else, plus `gcash_reference`/`proof_url` so the dashboard can show an Approve/Reject pair without a second call — cap the list UI at ~8 rows same as web, with a "View all bookings" link to the Bookings queue for the rest. Dashboard screen layout: sales-today hero card (with a vs-yesterday % badge, green if up / red if down) → sales-yesterday card → a 4-stat row (pending / confirmed today / checked-in today / courts in maintenance) → the needs-review list.

**Reports** (all three take `?from=&to=YYYY-MM-DD`, defaulting to the trailing 30 days like the web pages):

| Method | Path | Purpose |
|---|---|---|
| GET | `/admin/reports/bookings?from=&to=&court_id=` | Operational stats: status breakdown, court utilization, peak hours, cancellation reasons, rebook impact, no-shows, staff activity, match/game volume, courts currently under maintenance, plus a paginated `bookings` list (same summary shape) |
| GET | `/admin/reports/revenue?from=&to=` | Financial stats: `total_revenue`/`total_bookings`, daily `trend`, revenue `by_court`, `by_payment_method`, `by_source` (Front Desk vs Online), outstanding-payment aging (`pending_aging`), and `lost` revenue (rejected + cancelled, with reasons) |
| GET | `/admin/reports/clients?from=&to=` | `top_customers` (name, bookings, total spend — registered customers only), `new_vs_returning` counts, `guest_vs_registered` split |

These three mirror the web Booking/Revenue/Client Reports pages field-for-field (snake_case instead of camelCase) — build each as its own tab/screen with the same chart-and-table layout as the web version; there's no PDF/CSV export on mobile, that stays a web-only action.

**Settings** — one read hydrates all four sub-screens, four separate saves (matching the web's four separate forms/pages):

| Method | Path | Purpose |
|---|---|---|
| GET | `/admin/settings` | Full `OperatingHours` record — read once, use for the General, Time-of-day Groups, and Location screens |
| POST | `/admin/settings` | **General** tab. Body (multipart if `logo` included): `{ booking_widget_style, logo?, logo_height, show_brand_text, brand_text, payment_hold_minutes? }` |
| POST | `/admin/settings/logo/remove` | Remove the venue logo |
| POST | `/admin/settings/hours` | **Time-of-day Groups** tab. Body: `{ slot_length_minutes, period_morning_start, period_morning_end, period_afternoon_start, period_afternoon_end, period_evening_start, period_evening_end }` (all `HH:mm`). Morning-start doubles as opening time, evening-end as closing time — evening-end can be earlier in clock-time than evening-start (e.g. `17:00` to `02:00`) to mean "open past midnight," not an error. Saving this **regenerates upcoming court slots immediately**, so warn the user it takes a moment |
| POST | `/admin/settings/location` | **Location** tab. Body: `{ map_location?, map_lat, map_lng, map_style }` — `map_style` is one of `standard/light/dark/satellite/terrain`. Let the admin drop a pin on a map widget (e.g. `flutter_map`) and read back `lat`/`lng` from that, same as the web's click-to-pin Leaflet map |
| GET | `/admin/settings/rates` | **Court Rates** tab — same court list as `/admin/courts`, shown here as a simple "court name → price" edit list |
| PUT | `/admin/settings/rates/{court}` | Update one court's rate. Body: `{ "default_price" }` — reprices that court's still-open (`available`) slots immediately; already-booked slots keep their locked-in price |

Court/payment-methods/hero-images endpoints are unchanged from before:

| Method | Path | Purpose |
|---|---|---|
| GET | `/admin/courts` | All courts (incl. inactive), with `pending_bookings_count` |
| POST | `/admin/courts` | Create. Body: `{ "name", "location"?, "default_price" }` |
| PUT | `/admin/courts/{court}` | Update. Body: `{ "name", "location"?, "default_price", "is_active" }` |
| POST | `/admin/courts/{court}/toggle-active` | Show/hide from customers |
| POST | `/admin/courts/{court}/maintenance` | Toggle maintenance mode. Body when enabling: `{ "maintenance_reason"?, "maintenance_until"? }` |
| GET / POST | `/admin/payment-methods` | List / create (multipart with optional `qr` image) |
| POST | `/admin/payment-methods/{id}` | Update (also multipart — intentionally POST, not PUT, so file uploads work) |
| POST | `/admin/payment-methods/{id}/toggle-active` | Show/hide from customers |
| DELETE | `/admin/payment-methods/{id}` | Delete (blocked with a 422 if bookings reference it — surface that message) |
| GET / POST | `/admin/hero-images` | List / upload (multipart `images[]`, multiple files) |
| POST | `/admin/hero-images/{id}/move-up` / `move-down` | Reorder |
| DELETE | `/admin/hero-images/{id}` | Remove |

Admin screens: everything in staff, plus Dashboard (home screen for this role), Reports (3 tabs), Settings (4 tabs), Courts management (list + create/edit sheet + maintenance toggle), Payment methods list (CRUD + QR upload), Hero images grid (upload/reorder/delete).

## 5. Push notifications & device registration

`DeviceToken` records tie a Firebase Cloud Messaging token to a user. On login/app-resume: request notification permission, get the FCM token, and `POST /auth/device-token`. Re-register whenever `FirebaseMessaging.instance.onTokenRefresh` fires. Use this for: booking-status push to customers (approved/rejected), and new-booking push to staff/admin.

## 6. Design system — "Kitchen Line"

Implement as a Flutter `ThemeData` extension, not ad-hoc styling per screen.

**Brand mark:** small black rounded-square/circle badge with a dot pattern; use a paddle/sports icon (`Icons.sports_tennis` as placeholder) centered in a solid black circular container as the app's launcher badge and in-app logo mark.

**Colors** (define as `const` in a `AppColors` class):

| Token | Hex | Usage |
|---|---|---|
| `primaryGreen` | `#8BC63E` | Primary buttons, active states, links, accents |
| `ink` | `#171717` | Headings, dark section backgrounds, primary text |
| `surface` | `#FFFFFF` | Screen background, cards |
| `mutedGray` | `#6B7280` | Secondary/help text |
| `borderGray` | `#E5E7EB` | Input borders, card borders |
| `chipGreenBg` | `#F2F8E9` | Light green icon-circle backgrounds |
| `pillGreenBg` | `#8BC63E` | Filled pill buttons |

Text on `primaryGreen` fills: use `#1B1B1B` (near-black), not pure white — matches the brand's dark-on-green convention.

**Typography:** `google_fonts` with Poppins or Nunito. Headings bold, tight letter-spacing, large sizes. Body text regular weight, same family, `mutedGray` for secondary copy.

**Shape language:**
- Buttons: full pill shape, `BorderRadius.circular(999)`. Primary = solid `primaryGreen` fill with dark text (+ optional leading icon). Secondary = white/transparent fill, thin `borderGray` border, `ink` text.
- Cards: `BorderRadius.circular(24)`.
- Inputs: `BorderRadius.circular(16)`, `borderGray` border, no heavy shadows.
- Dark panel cards (solid `ink` background, white text, rounded top corners) — reuse this style for bottom sheets (e.g. the booking-confirmation sheet) and footer-style summary panels, mirroring the "Book a Court" header block from the web app.

Keep corners very rounded everywhere — this is the single most identifying trait of the visual system. Avoid sharp 0–8px radii on any interactive element.

## 7. Suggested project structure

```
lib/
  core/          # theme, constants (base URL, app token), dio client, secure storage
  data/          # models (freezed/json_serializable), repositories per resource
  features/
    auth/
    customer/    # court browsing, booking flow, my bookings, checkin QR
    staff/       # bookings queue, day schedule, checkin scanner
    admin/
      dashboard/
      reports/       # booking / revenue / client tabs
      settings/      # general / hours / rates / location tabs
      courts/
      payment_methods/
      hero_images/
  shared/        # role-aware shell/navigation, shared widgets (pill button, app card)
```

Route guarding: after `/auth/me` resolves, redirect to the customer shell, staff shell, or admin shell based on `role`. Admin gets staff's screens (Bookings, Day Schedule, Check-in) plus its own tabs (Dashboard, Reports, Settings, Courts, Payment methods, Hero images), so compose the admin shell from the staff shell's widgets rather than duplicating them — same relationship as the web sidebar, where staff just sees fewer nav items off the same underlying pages.

## 8. Out of scope / not yet built server-side

No customer-initiated booking cancellation endpoint exists yet (only admin cancel) — don't build a cancel button on the customer booking-detail screen unless you also add that endpoint. No password-reset endpoint exists yet either — omit "forgot password" from the login screen for v1.
