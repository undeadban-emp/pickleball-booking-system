<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    protected $fillable = [
        'name',
        'description',
        'location',
        'is_active',
        'default_price',
        'status',
        'maintenance_reason',
        'maintenance_until',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_price' => 'decimal:2',
            'maintenance_until' => 'datetime',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(CourtSlot::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === 'maintenance';
    }
}
