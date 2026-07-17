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

    public function contactName(): string
    {
        return $this->user->name ?? $this->guest_name ?? 'Guest';
    }

    public function isGuestBooking(): bool
    {
        return $this->user_id === null;
    }

    public function paymentProofUrl(): ?string
    {
        return $this->payment_proof_path ? asset('storage/'.$this->payment_proof_path) : null;
    }
}
