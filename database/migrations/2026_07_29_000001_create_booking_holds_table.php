<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks a booking currently on hold - which hours it used to occupy
     * (freed back to available for other customers) and what status it
     * should return to once the admin reschedules it to a new date/time.
     * Unlike booking_reschedule_logs (an append-only audit trail), this row
     * is mutated once resolved (rescheduled or cancelled outright).
     */
    public function up(): void
    {
        Schema::create('booking_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            $table->foreignId('from_court_id')->constrained('courts');
            $table->date('from_slot_date');
            $table->time('from_start_time');
            $table->time('from_end_time');

            $table->string('previous_status');
            $table->string('reason')->nullable();
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['booking_id', 'resolved_at']);
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_holds');
    }
};
