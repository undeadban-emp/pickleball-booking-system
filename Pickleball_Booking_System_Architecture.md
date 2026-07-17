# Pickleball Booking System Architecture

## Hosting

-   Hostinger (Laravel 12)
-   Cloudflare
-   MySQL

## Notifications

### Android

Use **Firebase Cloud Messaging (FCM)** for real push notifications. -
Free to send push notifications. - Works even when the app is closed.

### Admin

Use **Telegram Bot API** for backup/admin notifications.

### Email

Use Laravel Mail for booking confirmations and receipts.

## Real-Time Booking Table

Use **Polling** instead of WebSockets.

Recommended interval: - Admin Dashboard: every **5 seconds** - Customer
pages: every **15--30 seconds** if needed

### Optimized Polling

Instead of requesting the entire booking list:

`GET /api/bookings`

Use:

`GET /api/bookings/latest?last_id=250`

Return only newly created bookings.

Benefits: - Lower bandwidth - Faster responses - Less database load -
Works well with Cloudflare

## Database Recommendations

Create a table for FCM device tokens.

    device_tokens
    -------------
    id
    user_id
    fcm_token
    created_at
    updated_at

## Booking Flow

1.  Customer creates a booking.
2.  Laravel saves the booking.
3.  Admin dashboard receives the new booking through polling.
4.  Firebase sends a push notification to the Android app.
5.  Telegram sends a notification to administrators.
6.  Laravel Mail optionally sends a confirmation email.

## Android App Distribution

Provide an Admin page with: - Current app version - Release notes -
Download APK - Upload new APK (optional)

For public apps, prefer publishing through Google Play.

## Recommended Stack

  Component                    Technology
  ---------------------------- --------------------------------
  Backend                      Laravel 12
  Database                     MySQL
  Hosting                      Hostinger
  CDN / Security               Cloudflare
  Android Push Notifications   Firebase Cloud Messaging (FCM)
  Admin Notifications          Telegram Bot
  Email                        Laravel Mail
  Real-Time Dashboard          Polling (5 seconds)

## Future Improvements

If the system grows significantly: - Replace polling with Laravel Reverb
for true real-time updates. - Keep FCM for Android notifications. - Keep
Telegram for admin alerts.

---

# Booking Domain Plan

The sections above cover infrastructure. This section defines the actual booking domain: courts, fixed time slots, roles, and the GCash payment/verification flow.

## Core Rules

- Fixed time slots per court, generated within a **global operating-hours window (from/to)** that admin configures — applies to all courts uniformly, not per-court.
- Customers can select **multiple contiguous slots in one booking** (e.g. 1-2 and 2-3 together as a single 2-hour booking). Slots must form an unbroken block; gaps are not allowed.
- Four roles: customer, admin, court staff/front-desk (limited), and an admin-only Android app (FCM push + APK distribution).
- Payment: GCash manual flow — customer pays to a GCash number/QR, submits a reference number, booking sits `pending_payment` until **admin manually approves/rejects**. On approval, customer gets a check-in QR (for front-desk) and a receipt/booking-details link.
- Courts can be put into **maintenance mode**, hiding them from booking and blocking new bookings until reactivated.

## Database Schema

**Slot strategy:** a single `operating_hours` settings row (`open_time`, `close_time`, `slot_length_minutes` — e.g. 60) is admin-configurable and applies globally to all courts. A scheduled command materializes concrete `court_slots` rows per court for a rolling window (e.g. 30–60 days ahead), generating one row per `slot_length_minutes` interval between `open_time` and `close_time`, via `insertOrIgnore` on a unique `(court_id, slot_date, start_time)` key. This gives a hard row to lock against for double-booking prevention and allows per-date/per-slot overrides (manual blocking, price override) without touching the global setting.

**Multi-slot bookings:** a booking can cover more than one contiguous slot (e.g. 1-2 + 2-3), so `bookings` doesn't hold a single `court_slot_id` — instead a `booking_slots` pivot table links a booking to N slots. Contiguity (no gaps) is validated in the booking-creation service before the transaction runs.

- **`users`**: `role` (enum: `customer|admin|staff`), `phone`, standard Laravel auth fields.
- **`operating_hours`**: single-row settings table — `open_time`, `close_time`, `slot_length_minutes`, admin-editable.
- **`courts`**: `name`, `description`, `location`, `is_active`, `default_price` (per-slot price; used unless a slot has its own override), `status` (enum: `active|maintenance`), `maintenance_reason` (nullable string), `maintenance_until` (nullable date/datetime).
- **`court_slots`** (bookable instances, generated from `operating_hours`): `court_id`, `slot_date`, `start_time`, `end_time`, `price`, `status` (`available|booked|blocked`). Unique `(court_id, slot_date, start_time)`; index `(court_id, slot_date, status)`.
- **`bookings`**: `booking_code` (unique human-friendly code), `user_id`, `court_id`, `status` (`pending_payment|confirmed|rejected|cancelled|completed|no_show`), `total_price` (sum of all linked slot prices), `gcash_reference`, `gcash_submitted_at`, `payment_reviewed_by`, `payment_reviewed_at`, `rejection_reason`, `checkin_token` (unique, nullable — one QR covers the whole multi-slot booking), `checkin_token_expires_at` (set to the end time of the *last* slot), `checked_in_at`, `checked_in_by`, `receipt_token` (unique, nullable), `cancelled_at`, `cancellation_reason`. Index `(status, created_at)` for the admin polling endpoint.
- **`booking_slots`** (pivot): `booking_id`, `court_slot_id`. Unique on `court_slot_id` — a slot can belong to at most one active booking; this is where the anti-double-booking uniqueness lives.
- **`device_tokens`**: `user_id`, `fcm_token` (unique), `device_name`, `last_used_at`.
- **`app_releases`** (Android admin-app APK distribution): `version`, `version_code` (unique), `release_notes`, `apk_path`, `file_size`, `is_active`, `uploaded_by`.
- **`booking_status_logs`** (audit trail): `booking_id`, `from_status`, `to_status`, `changed_by`, `note`.
- **`telegram_notification_logs`** (optional, for debugging failed sends): `booking_id`, `payload` (json), `status`, `error_message`.

Relationships: `User hasMany DeviceTokens, Bookings`; `Court hasMany CourtSlots`; `CourtSlot belongsTo Court; belongsToMany Bookings through booking_slots`; `Booking belongsTo User, Court; belongsToMany CourtSlots through booking_slots; hasMany BookingStatusLogs`.

## Roles & Permissions

Simple `role` enum column on `users` (`App\Enums\UserRole`) — no permissions package needed for 4 fixed roles. Use Gates/Policies + a custom `role:admin,staff` middleware, and helper methods on `User` (`isAdmin()`, `isStaff()`, etc). The Android app authenticates as an admin or staff user via Sanctum token — it's a client type, not a 5th role.

**Assumption (confirm during build):** staff = front-desk only — can view bookings and check customers in, but cannot approve/reject payments, manage courts/pricing, or upload APKs. Admin has full authority.

## Booking Flow & Anti-Double-Booking

0. **Maintenance gate**: a court with `status = maintenance` is excluded from `GET /courts` (customer-facing list) and its slots endpoint returns 423 Locked with the `maintenance_reason`/`maintenance_until`. Admin toggles this via `PATCH admin/courts/{id}/maintenance` (`{status: maintenance|active, reason, until}`) — does **not** auto-cancel existing `confirmed` bookings, but blocks new bookings and hides the court from browsing while active. The slot-generation command also skips courts under maintenance.
1. `GET /courts/{court}/slots?date=` — availability query against `court_slots.status = available`; response ordered by `start_time` so the client can offer "select a contiguous range" UI (e.g. click 1-2 then 2-3).
2. `POST /bookings` — body takes an array of `court_slot_id`s. The service first validates **contiguity**: sorted slots' `end_time` of slot N must equal `start_time` of slot N+1, all on the same court/date — reject with 422 otherwise. Then wrapped in `DB::transaction()`, all selected `court_slots` are locked with `CourtSlot::whereIn('id', $ids)->lockForUpdate()->get()`; if any slot isn't `available`, the whole booking is rejected with 409 (all-or-nothing). Otherwise all slots flip to `booked`, one `booking` row is inserted as `pending_payment` with `total_price` = sum of slot prices, and `booking_slots` rows link each slot.
3. `POST /bookings/{id}/gcash-reference` — customer submits reference number; booking stays `pending_payment` (now awaiting review).
4. Admin reviews manually (outside the system) and calls `approve` or `reject`.
   - **Approve**: generate `checkin_token` + `receipt_token`, status → `confirmed`, fire `BookingConfirmed`.
   - **Reject**: release all linked slots back to `available`, status → `rejected`, fire `BookingRejected`.
5. Front desk scans check-in QR → `checkin.validate` then `checkin.confirm` → `checked_in_at` set, status → `completed`. Token can't be reused (checked via `checked_in_at IS NULL`).
6. Recommended safeguard: scheduled `bookings:expire-pending` job cancels stale `pending_payment` bookings (e.g. >60 min with no admin action) and releases the slot.

State machine implemented as `App\Services\BookingStatusService` with explicit transition methods (`approve()`, `reject()`, `cancel()`, `checkIn()`, `markCompleted()`) — each validates current status, writes a `booking_status_logs` row, and fires the relevant event.

## API Endpoints (Sanctum auth, `/api/v1` prefix)

- **Customer**: `GET courts`, `GET courts/{court}/slots`, `POST register|login|logout`, `POST bookings`, `POST bookings/{id}/gcash-reference`, `GET bookings/{id}` (status polling), `GET bookings/mine`, `GET receipts/{receipt_token}` (public), `GET bookings/{id}/checkin-qr`.
- **Admin**: `GET admin/bookings`, `GET admin/bookings/latest?last_id=` (the incremental polling endpoint — returns only rows with `id > last_id`, ordered/limited), `POST admin/bookings/{id}/approve|reject|cancel`, CRUD `admin/courts`, `PATCH admin/courts/{id}/maintenance` (toggle maintenance mode + reason/until), `GET/PUT admin/operating-hours` (global open/close/slot-length setting), `PATCH admin/slots/{id}/block|unblock` (ad-hoc single-slot override, distinct from court-wide maintenance).
- **Check-in**: `POST checkin/validate` (role admin/staff), `POST checkin/{id}/confirm`.
- **Android admin app**: `POST device-tokens`, `DELETE device-tokens/{token}`, `GET app/releases/latest`, `GET app/releases` (admin), `POST admin/app-releases` (upload, multipart), `GET app-releases/{id}/download`.

## QR Codes

Package: `simplesoftwareio/simple-qrcode`.

- **GCash QR**: the business's real, static GCash-issued QR image (no dynamic generation — no GCash merchant API in scope). Store as an asset, serve via `GET payment-info` (`{gcash_number, gcash_qr_url}`).
- **Check-in QR** (security-sensitive): encodes a URL/deep-link containing a random `checkin_token` (`Str::random(40)`), **not** the raw booking ID. Server validates: booking is `confirmed`, token not expired, `checked_in_at IS NULL` (prevents replay). Token generated only at approval time, never at creation.
- **Receipt link**: separate long-lived `receipt_token`, public route shows non-sensitive booking info only (no full GCash reference).

## Notifications Wiring

Events (`BookingCreated`, `BookingConfirmed`, `BookingRejected`, `BookingCheckedIn`) fired explicitly from `BookingStatusService` transition methods, handled by **queued** listeners (`ShouldQueue`):

- `SendTelegramNewBookingAlert` — `App\Services\TelegramNotifier` wrapping `Http::` calls to Telegram Bot API, config in `config/services.php`.
- `SendFcmNewBookingPush` — via `kreait/firebase-php` (FCM v1 API), pushes to all admin/staff `device_tokens`; prunes tokens on `NotRegistered` errors.
- `SendBookingConfirmedNotifications` / `SendBookingRejectedNotification` — Laravel `Notification` classes (mail channel) with the check-in QR embedded and receipt link.

Queue driver: **database** (simplest on Hostinger; no Redis dependency). Cron-triggered `schedule:run` handles both slot generation and pending-booking expiry.

## Project Structure

Standard Laravel 12 layout: `app/Enums` (UserRole, BookingStatus, CourtSlotStatus), `app/Models`, `app/Services` (BookingStatusService, SlotAvailabilityService, TelegramNotifier, FcmPushService, QrCodeService), `app/Events`, `app/Listeners`, `app/Notifications`, `app/Http/Controllers/Api` (+ `Admin` subfolder), `app/Http/Requests`, `app/Http/Resources`, `app/Policies`, `app/Console/Commands` (`GenerateCourtSlots`, `ExpirePendingBookings`). Migrations/factories/seeders under `database/`. Feature tests per milestone, especially for the double-booking race condition and check-in token reuse.

## Build Order

1. **Scaffold + Auth + Roles** — `composer create-project laravel/laravel`, Sanctum, `users`/`courts` migrations, role middleware, seeded admin+staff users. *Verify: login per role, middleware blocks correctly.*
2. **Courts & Slots + Availability + Maintenance** — `operating_hours` + `court_slots` migrations, `slots:generate` command + scheduler (skips maintenance courts), admin CRUD for courts, maintenance toggle endpoint, availability endpoint. *Verify: generate slots per global operating hours, query availability, unique constraint holds, maintenance court hidden/blocked.*
3. **Booking Creation + GCash Submission** — `bookings`/`booking_slots` migrations, `BookingStatusService` skeleton, contiguity validation, locked multi-slot transaction on create, reference submission endpoint. *Verify: concurrent booking on same slot → one 200, one 409; non-contiguous slot selection rejected with 422.*
4. **Admin Approval + Check-in QR + Receipt** — approve/reject endpoints, token generation, `QrCodeService`, check-in validate/confirm, public receipt route. *Verify: full happy path create→submit→approve→scan→check-in; reused token rejected.*
5. **Notifications** — events/listeners, Telegram + FCM integration, `device_tokens` registration, Mail notifications with embedded QR. *Verify: booking creation triggers Telegram + FCM; approval triggers email with QR + link.*
6. **Admin Polling Endpoint** — `admin/bookings/latest?last_id=`, index tuning, `Cache-Control: no-store` (dynamic data behind Cloudflare). *Verify: incremental results only, stays small under load.*
7. **Android Admin App — APK Distribution** — `app_releases` table, upload/list/latest/download endpoints. *Verify: upload dummy APK, fetch metadata, download integrity checks out.*
8. **Polish/Hardening** — `bookings:expire-pending` job, audit logging on every transition, rate-limiting on public endpoints, form request validation coverage, Feature tests for state machine + race conditions.

## Verification

- Each milestone ships with at least one Feature test (Pest/PHPUnit) proving its critical invariant (e.g. milestone 3: double-booking impossible under concurrent requests; milestone 4: check-in token can't be reused).
- Manually exercise the full booking → payment → approval → check-in path locally via `php artisan serve` + API client (Postman/Insomnia) before considering a milestone done.
- Confirm Telegram/FCM/Mail actually deliver using real (sandbox) credentials, not just that jobs dispatch without error.
