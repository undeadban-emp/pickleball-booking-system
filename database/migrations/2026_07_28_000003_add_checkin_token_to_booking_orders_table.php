<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One check-in code for the whole order, set alongside the individual
     * bookings' own checkin_token when the order is approved - so a
     * multi-session customer can show a single QR code at the gate instead
     * of one per session. Front desk scanning this still lists every
     * session in the order so staff pick the specific one being checked in;
     * the per-booking tokens (and the API check-in flow) are untouched.
     */
    public function up(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            $table->string('checkin_token')->nullable()->unique()->after('cancellation_reason');
            $table->dateTime('checkin_token_expires_at')->nullable()->after('checkin_token');
        });
    }

    public function down(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            $table->dropColumn(['checkin_token', 'checkin_token_expires_at']);
        });
    }
};
