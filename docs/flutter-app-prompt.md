# Flutter Build Prompt — Kitchen Line

Paste everything below into a fresh conversation with a coding agent (or use it yourself) to scaffold the Flutter client for the Kitchen Line pickleball booking system. It targets the Laravel API already implemented in this repo at `routes/api.php`.

---

## 1. What you're building

A single Flutter app (Android + iOS) with **three role-driven experiences** behind one login screen:

- **Customer** — browse courts, check availability, book a slot, pay via GCash, view/track their bookings, show a QR check-in code.
- **Staff** — view bookings, scan/validate check-in codes, confirm check-ins.
- **Admin** — everything staff can do, plus approve/reject/cancel bookings, manage courts, operating-hour settings, payment methods, and homepage hero images.

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
| GET | `/bookings/mine` | Paginated list of the logged-in user's bookings, with `court` and `slots` loaded |
| POST | `/bookings` | Create a booking. Body: `{ "court_id": 1, "court_slot_ids": [12, 13] }` (1–6 contiguous slots). Returns the booking plus `payment_info` (GCash number/QR) |
| GET | `/bookings/{booking}` | Booking detail (court, slots, status history) — 403s if it's not the caller's booking |
| POST | `/bookings/{booking}/gcash-reference` | Submit GCash payment reference after transferring. Body: `{ "gcash_reference": "..." }` |
| GET | `/bookings/{booking}/checkin-qr` | Returns `{ checkin_token, checkin_url, expires_at }` once the booking is `confirmed` — render `checkin_token` as a QR code (e.g. `qr_flutter`) for the customer to show at the front desk |

Customer booking flow screens: Court list → date/slot picker (use `/availability` for the grid) → review & confirm → payment instructions (GCash QR + reference field) → booking status (pending/confirmed) → QR check-in code once confirmed.

### Staff (`role: staff`, also usable by admin)
| Method | Path | Purpose |
|---|---|---|
| GET | `/admin/bookings?status=&court_id=` | Paginated booking list with filters |
| GET | `/admin/bookings/latest?last_id=` | Polling endpoint for new bookings since `last_id` — use for a live front-desk board |
| POST | `/admin/bookings/{booking}/approve` | Approve a pending-payment booking — staff and admin both |
| POST | `/admin/bookings/{booking}/reject` | Body: `{ "reason"? }` — staff and admin both |
| POST | `/admin/bookings/{booking}/cancel` | Body: `{ "reason"? }` — staff and admin both |
| POST | `/checkin/validate` | Body: `{ "token": "..." }` — validate a scanned/typed check-in code before confirming |
| POST | `/checkin/{booking}/confirm` | Mark the booking checked in |

Staff screens: Bookings queue (with status filter chips + approve/reject/cancel actions), Check-in scanner (camera QR scan via `mobile_scanner` → auto-fill `/checkin/validate` → confirm button).

### Admin (`role: admin`) — everything staff has, plus court/settings/catalog management:
| Method | Path | Purpose |
|---|---|---|
| GET | `/admin/courts` | All courts (incl. inactive), with `pending_bookings_count` |
| POST | `/admin/courts` | Create. Body: `{ "name", "location"?, "default_price" }` |
| PUT | `/admin/courts/{court}` | Update. Body: `{ "name", "location"?, "default_price", "is_active" }` |
| POST | `/admin/courts/{court}/toggle-active` | Show/hide from customers |
| POST | `/admin/courts/{court}/maintenance` | Toggle maintenance mode. Body when enabling: `{ "maintenance_reason"?, "maintenance_until"? }` |
| GET | `/admin/settings` | Operating hours / booking-widget config |
| POST | `/admin/settings` | Update (multipart if `logo` file included) — see field list in `SettingsController` |
| POST | `/admin/settings/logo/remove` | Remove the venue logo |
| GET / POST | `/admin/payment-methods` | List / create (multipart with optional `qr` image) |
| POST | `/admin/payment-methods/{id}` | Update (also multipart — intentionally POST, not PUT, so file uploads work) |
| POST | `/admin/payment-methods/{id}/toggle-active` | Show/hide from customers |
| DELETE | `/admin/payment-methods/{id}` | Delete (blocked with a 422 if bookings reference it — surface that message) |
| GET / POST | `/admin/hero-images` | List / upload (multipart `images[]`, multiple files) |
| POST | `/admin/hero-images/{id}/move-up` / `move-down` | Reorder |
| DELETE | `/admin/hero-images/{id}` | Remove |

Admin screens: everything in staff (including the same booking approve/reject/cancel actions), plus Courts management (list + create/edit sheet + maintenance toggle), Settings form (hours, slot length, logo), Payment methods list (CRUD + QR upload), Hero images grid (upload/reorder/delete).

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
    staff/       # bookings list, checkin scanner
    admin/       # bookings queue, courts, settings, payment methods, hero images
  shared/        # role-aware shell/navigation, shared widgets (pill button, app card)
```

Route guarding: after `/auth/me` resolves, redirect to the customer shell, staff shell, or admin shell based on `role`. Admin gets staff's screens plus its own tabs, so consider composing the admin shell from the staff shell's widgets rather than duplicating them.

## 8. Out of scope / not yet built server-side

No customer-initiated booking cancellation endpoint exists yet (only admin cancel) — don't build a cancel button on the customer booking-detail screen unless you also add that endpoint. No password-reset endpoint exists yet either — omit "forgot password" from the login screen for v1.
