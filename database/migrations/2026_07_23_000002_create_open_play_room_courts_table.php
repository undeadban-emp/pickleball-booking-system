<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_play_room_courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_play_room_id')->constrained('open_play_rooms')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();

            // The host's own pre-existing confirmed booking that justifies using
            // this court - Open Play never creates a Booking itself, only
            // references one, so unique() here means a paid slot can back at
            // most one room.
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete()->unique();

            $table->timestamps();

            $table->unique(['open_play_room_id', 'court_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_room_courts');
    }
};
