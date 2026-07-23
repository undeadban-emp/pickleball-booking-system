<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Booking extends Model
{
    protected $fillable = [
        'booking_code',
        'user_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'court_id',
        'rebooked_from_id',
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
                    ->orWhere(fn (Builder $q2) => $q2->where('bookings.status', 'cancelled')->whereHas('rebookedTo'));
            })
            ->whereNull('bookings.rebooked_from_id');
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
     */
    public function rebookedFrom(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rebooked_from_id');
    }

    /**
     * The booking this one was rescheduled into, if any.
     */
    public function rebookedTo(): HasMany
    {
        return $this->hasMany(Booking::class, 'rebooked_from_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
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

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
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
     * True once the customer has sent a GCash reference for a pending_payment
     * booking - it's now sitting in the admin review queue, not actually
     * waiting on the customer to pay anymore.
     */
    public function hasSubmittedPayment(): bool
    {
        return filled($this->gcash_reference);
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
