<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_play_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_play_room_id')->constrained('open_play_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('current_status')->default('waiting');
            // waiting|playing|left

            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);

            // Set at join, reset to now() the instant this player's match ends.
            // Replaces a static "waiting_order" int - longest-waiting changes
            // every round, so it needs to be a timestamp the matchmaking service
            // sorts on live, not a number that would need constant renumbering.
            $table->dateTime('available_since')->nullable();

            $table->dateTime('left_at')->nullable();

            $table->timestamps();

            $table->unique(['open_play_room_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_players');
    }
};
