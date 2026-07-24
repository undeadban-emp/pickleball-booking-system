<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Points at the booking a remainder piece was split off from,
            // when an admin partially reschedules a booking (e.g. only the
            // rained-out middle hour of an 8-11am booking moves; 8-9 and
            // 10-11 split off into their own sibling booking rows that keep
            // their original date/time). Distinct from rescheduled_from_id
            // (legacy cancel+recreate mechanism, excluded from sales totals)
            // since a split sibling represents real, already-paid revenue
            // that must still count.
            $table->foreignId('split_from_booking_id')->nullable()->after('rescheduled_from_id')
                ->constrained('bookings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('split_from_booking_id');
        });
    }
};
