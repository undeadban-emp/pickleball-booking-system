<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * In-place reschedule history: moving a booking to a new date/time no
     * longer cancels it and creates a replacement row (that was the old
     * mechanism, still visible via bookings.rescheduled_from_id on any rows
     * created that way before this table existed) - it now just swaps the
     * booking's slots and records the move here, so the booking keeps its
     * id/booking_code/status throughout its whole lifetime.
     */
    public function up(): void
    {
        Schema::create('booking_reschedule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            $table->foreignId('from_court_id')->constrained('courts');
            $table->date('from_slot_date');
            $table->time('from_start_time');
            $table->time('from_end_time');

            $table->foreignId('to_court_id')->constrained('courts');
            $table->date('to_slot_date');
            $table->time('to_start_time');
            $table->time('to_end_time');

            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['booking_id', 'created_at']);
            $table->index('from_slot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reschedule_logs');
    }
};
