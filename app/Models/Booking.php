<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
