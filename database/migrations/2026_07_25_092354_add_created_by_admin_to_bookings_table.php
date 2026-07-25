<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Distinguishes a walk-in booking staff entered on a guest's
            // behalf from one the guest submitted themselves through the
            // public booking flow - only the latter is safe for admin to
            // later edit (add/remove hours, rename), since a walk-in's
            // details were already staff-entered and confirmed on the spot.
            $table->boolean('created_by_admin')->default(false)->after('guest_email');
        });

        // Backfill: the only existing signal for "admin walked this booking
        // in" is the fixed note BookingService::createConfirmedBooking()
        // logs on approval - no dedicated column existed before this one.
        DB::table('bookings')
            ->whereIn('id', function ($query) {
                $query->select('booking_id')
                    ->from('booking_status_logs')
                    ->where('note', 'Booked by admin, payment bypassed');
            })
            ->update(['created_by_admin' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('created_by_admin');
        });
    }
};
