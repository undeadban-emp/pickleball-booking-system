<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Nullable: only set when a checkout produced 2+ contiguous
            // groups (multiple sessions under one payment). A single-session
            // booking (the common case, including admin walk-ins and
            // reschedules) is created exactly as before with this left null.
            $table->foreignId('booking_order_id')->nullable()->after('rescheduled_from_id')
                ->constrained('booking_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_order_id');
        });
    }
};
