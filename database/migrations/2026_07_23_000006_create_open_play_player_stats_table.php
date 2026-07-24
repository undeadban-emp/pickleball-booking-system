<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cross-session history/rank - one row per player, persists across all
        // rooms, unlike open_play_players which is per-room.
        Schema::create('open_play_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->unique();

            $table->unsignedInteger('total_games')->default(0);
            $table->unsignedInteger('total_wins')->default(0);
            $table->unsignedInteger('total_losses')->default(0);

            // Cached rather than derived live, so profile/leaderboard reads
            // don't need to aggregate open_play_match_players on every request.
            $table->decimal('win_rate', 5, 2)->default(0);
            $table->string('rank')->default('Novice');

            $table->dateTime('last_played_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_player_stats');
    }
};
