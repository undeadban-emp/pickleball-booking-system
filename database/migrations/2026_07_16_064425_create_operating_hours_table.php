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
        Schema::create('operating_hours', function (Blueprint $table) {
            $table->id();
            $table->time('open_time');
            $table->time('close_time');
            $table->unsignedSmallInteger('slot_length_minutes')->default(60);
            $table->string('gcash_number')->nullable();
            $table->string('gcash_qr_path')->nullable();
            $table->string('booking_widget_style')->default('grid'); // grid|by_court

            // Where each time-of-day group starts on the booking widget. "Late evening"
            // wraps past midnight into the next calendar day up until "Morning" starts.
            $table->time('period_morning_start')->default('07:00:00');
            $table->time('period_afternoon_start')->default('12:00:00');
            $table->time('period_evening_start')->default('17:00:00');
            $table->time('period_late_evening_start')->default('00:00:00');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operating_hours');
    }
};
