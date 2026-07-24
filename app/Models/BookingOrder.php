<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingOrder extends Model
{
    protected $fillable = [
        'receipt_token',
        'user_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'total_price',
        'status',
        'payment_method_id',
        'gcash_reference',
        'payment_proof_path',
        'gcash_submitted_at',
        'payment_reviewed_by',
        'payment_reviewed_at',
        'rejection_reason',
        'cancelled_at',
        'cancellation_reason',
        'checkin_token',
        'checkin_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
            'gcash_submitted_at' => 'datetime',
            'payment_reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checkin_token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_reviewed_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function contactName(): string
    {
        return $this->user->name ?? $this->guest_name ?? 'Guest';
    }

    public function contactEmail(): ?string
    {
        return $this->user->email ?? $this->guest_email;
    }

    public function paymentProofUrl(): ?string
    {
        return $this->payment_proof_path ? asset('storage/'.$this->payment_proof_path) : null;
    }

    public function hasSubmittedPayment(): bool
    {
        return filled($this->gcash_reference) || filled($this->payment_proof_path);
    }
}
