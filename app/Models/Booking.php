<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_code',
        'user_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'court_id',
        'rescheduled_from_id',
        'split_from_booking_id',
        'booking_order_id',
        'status',
        'total_price',
        'payment_method_id',
        'gcash_reference',
        'payment_proof_path',
        'gcash_submitted_at',
        'payment_reviewed_by',
        'payment_reviewed_at',
        'rejection_reason',
        'checkin_token',
        'checkin_token_expires_at',
        'checked_in_at',
        'checked_in_by',
        'receipt_token',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
            'gcash_submitted_at' => 'datetime',
            'payment_reviewed_at' => 'datetime',
            'checkin_token_expires_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Bookings that represent money actually confirmed received - excludes
     * pending/rejected bookings, and excludes the "old" side of a rebook
     * (rain/reschedule) since no new money changed hands there, only the
     * new booking it moved to should count.
     */
    public function scopeSales(Builder $query): Builder
    {
        // Columns are qualified with `bookings.` because callers often join
        // in other tables (courts, payment_methods, ...) that also have a
        // `status` column - unqualified would throw "ambiguous column".
        return $query
            ->where(function (Builder $q) {
                $q->whereIn('bookings.status', ['confirmed', 'completed'])
                    ->orWhere(fn (Builder $q2) => $q2->where('bookings.status', 'cancelled')->whereHas('rescheduledTo'));
            })
            ->whereNull('bookings.rescheduled_from_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * The earlier booking this one replaces (e.g. rained out and rescheduled)
     * - no new money changes hands, it's a continuation of the same payment.
     * Legacy: reschedules now update the same booking in place (see
     * rescheduleLogs() below) instead of creating a new row, so this only
     * ever points at something for bookings rescheduled before that change.
     */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rescheduled_from_id');
    }

    /**
     * The booking this one was rescheduled into, if any. Legacy, see
     * rescheduledFrom() above.
     */
    public function rescheduledTo(): HasMany
    {
        return $this->hasMany(Booking::class, 'rescheduled_from_id');
    }

    /**
     * History of in-place reschedules (e.g. rained out, moved to a new
     * date/time) - the booking keeps its id/booking_code/status throughout,
     * this is just a log of where it used to be and where it moved to.
     */
    public function rescheduleLogs(): HasMany
    {
        return $this->hasMany(BookingRescheduleLog::class)->orderBy('created_at');
    }

    /**
     * The booking a remainder piece was split off from, when a partial
     * reschedule moves only some of a booking's hours (e.g. only the
     * rained-out middle hour of an 8-11am booking) and the untouched hours
     * split into their own sibling booking row that keeps its original
     * date/time. See BookingService::splitAndReschedule().
     */
    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'split_from_booking_id');
    }

    /**
     * Sibling booking(s) split off from this one, if any. See splitFrom()
     * above.
     */
    public function splitSiblings(): HasMany
    {
        return $this->hasMany(Booking::class, 'split_from_booking_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * The multi-session order this booking was created as part of, if any -
     * see BookingOrder for the "one payment, several sessions" flow.
     */
    public function bookingOrder(): BelongsTo
    {
        return $this->belongsTo(BookingOrder::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_reviewed_by');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function slots(): BelongsToMany
    {
        return $this->belongsToMany(CourtSlot::class, 'booking_slots');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class);
    }

    /**
     * Every hold this booking has ever been placed on, history included -
     * callers wanting just the currently-active one (if any) should add
     * ->whereNull('resolved_at') themselves. See BookingService::holdSlots().
     */
    public function holds(): HasMany
    {
        return $this->hasMany(BookingHold::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    /**
     * The Open Play room this booking's court/timeframe was opened into, if any.
     */
    public function openPlayRoomCourt(): HasOne
    {
        return $this->hasOne(OpenPlayRoomCourt::class);
    }

    public function hasMatch(): bool
    {
        return $this->matches()->exists();
    }

    public function contactName(): string
    {
        return $this->user->name ?? $this->guest_name ?? 'Guest';
    }

    public function contactPhone(): ?string
    {
        return $this->user->phone ?? $this->guest_phone;
    }

    public function contactEmail(): ?string
    {
        return $this->user->email ?? $this->guest_email;
    }

    public function isGuestBooking(): bool
    {
        return $this->user_id === null;
    }

    /**
     * One display-ready "M j, Y, g:i A to g:i A" line per booked slot.
     * Expects `slots` to already be eager-loaded (ordered by start_time) -
     * used by every API surface that shows a booking's court time to a
     * human (customer app, staff check-in prompt), so the formatting only
     * lives in one place.
     */
    public function scheduleLines(): array
    {
        return $this->slots
            ->map(fn (CourtSlot $slot) => sprintf(
                '%s, %s to %s',
                $slot->slot_date->format('M j, Y'),
                Carbon::parse($slot->start_time)->format('g:i A'),
                Carbon::parse($slot->end_time)->format('g:i A'),
            ))
            ->all();
    }

    public function statusLabel(): string
    {
        return static::labelForStatus($this->status);
    }

    public static function labelForStatus(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    /**
     * Flat, display-ready fields shared by every API listing/detail
     * response that shows a booking summary - formatted server-side so
     * clients never reformat a 24h time string or guess at a status label.
     * Expects `court` and `slots` to already be eager-loaded.
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->booking_code,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'customer' => $this->contactName(),
            'phone' => $this->contactPhone(),
            'email' => $this->contactEmail(),
            'court' => $this->court->name,
            'schedule' => $this->scheduleLines(),
            'total' => $this->total_price,
        ];
    }

    public function paymentProofUrl(): ?string
    {
        return $this->payment_proof_path ? asset('storage/'.$this->payment_proof_path) : null;
    }

    /**
     * Public URL for a PNG check-in QR encoding this booking's `checkin_token`
     * (same value the front-desk scanner expects - see
     * BookingService::checkIn()), generated once and cached on the public
     * disk since the token doesn't change after approve() sets it.
     *
     * Rendered as a real PNG file (not inline SVG/data-URI) because most
     * email clients (Gmail, Outlook, etc.) strip <svg> tags from HTML email
     * bodies - a real image URL is the only option that reliably displays
     * everywhere. simple-qrcode's PNG backend needs the Imagick extension,
     * which this server doesn't have, so this rasterizes the QR matrix
     * directly with GD (already installed) using bacon-qr-code's low-level
     * encoder (a transitive dependency of simple-qrcode already in use).
     * Null until the booking is confirmed and has a token to encode.
     */
    public function checkinQrUrl(): ?string
    {
        if (! $this->checkin_token) {
            return null;
        }

        $path = 'booking-qr/'.$this->checkin_token.'.png';

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $this->renderCheckinQrPng());
        }

        return asset('storage/'.$path);
    }

    protected function renderCheckinQrPng(): string
    {
        $matrix = Encoder::encode($this->checkin_token, ErrorCorrectionLevel::M())->getMatrix();
        $moduleCount = $matrix->getWidth();

        $moduleSize = 8;
        $quietZone = 4;
        $imageSize = ($moduleCount + $quietZone * 2) * $moduleSize;

        $image = imagecreatetruecolor($imageSize, $imageSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $imageSize, $imageSize, $white);

        for ($y = 0; $y < $moduleCount; $y++) {
            for ($x = 0; $x < $moduleCount; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = ($x + $quietZone) * $moduleSize;
                    $py = ($y + $quietZone) * $moduleSize;
                    imagefilledrectangle($image, $px, $py, $px + $moduleSize - 1, $py + $moduleSize - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /**
     * True once the customer has sent a GCash reference and/or a proof
     * screenshot for a pending_payment booking - it's now sitting in the
     * admin review queue, not actually waiting on the customer to pay
     * anymore. The reference is optional (proof of payment is the required
     * field), so either one alone counts as submitted.
     */
    public function hasSubmittedPayment(): bool
    {
        return filled($this->gcash_reference) || filled($this->payment_proof_path);
    }

    /**
     * Who/what actually cancelled this booking - the `cancellation_reason`
     * column alone can't tell an admin apart from a self-service customer
     * cancel (both can leave it null), so this also looks at the matching
     * status-log entry's `changed_by` to attribute it correctly. Expects
     * `statusLogs.changedBy` to already be eager-loaded.
     */
    public function cancellationSummary(): ?string
    {
        if ($this->status !== 'cancelled') {
            return null;
        }

        if ($this->cancellation_reason === 'Payment window expired') {
            return 'Auto-cancelled — payment window expired';
        }

        $log = $this->statusLogs->sortByDesc('created_at')->firstWhere('to_status', 'cancelled');

        $who = $log?->changedBy ? "by {$log->changedBy->name}" : 'by the customer';
        $reason = $this->cancellation_reason ? " — {$this->cancellation_reason}" : '';

        return "Cancelled {$who}{$reason}";
    }
}
