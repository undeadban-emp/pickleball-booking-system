<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_play_match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_play_match_id')->constrained('open_play_matches')->cascadeOnDelete();

            // Room-scoped identity, carries games/wins/losses for that room.
            $table->foreignId('open_play_player_id')->constrained('open_play_players')->cascadeOnDelete();

            // Denormalized direct reference so partner/opponent-history queries
            // and "my matches" lookups don't have to join through open_play_players.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('team');
            // 1 or 2

            $table->timestamps();

            $table->unique(['open_play_match_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_match_players');
    }
};
