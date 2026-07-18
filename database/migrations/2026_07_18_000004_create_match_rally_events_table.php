<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_rally_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_game_id')->constrained('match_games')->cascadeOnDelete();

            $table->unsignedInteger('sequence'); // order within the game, for history navigation

            $table->string('event_type');
            // point|side_out|timeout|switch_sides|technical_warning|technical_foul|verbal_warning|medical_timeout|delay

            $table->unsignedTinyInteger('acting_team')->nullable(); // team the event applies to

            $table->unsignedSmallInteger('team1_score_after');
            $table->unsignedSmallInteger('team2_score_after');
            $table->unsignedTinyInteger('serving_team_after');
            $table->unsignedTinyInteger('server_position_after')->nullable();
            $table->unsignedTinyInteger('server_number_after')->default(1);

            // Snapshot of every player's court side at this exact moment (id => 1|2).
            // The three columns above only capture the SERVING side's arrangement;
            // this covers both teams, since a side that isn't currently serving keeps
            // whatever arrangement it had from its last service turn — Rally History
            // playback needs the full picture, not just who's serving right now.
            $table->json('player_positions_after')->nullable();

            $table->json('meta')->nullable(); // free-form notes (e.g. warning reason)

            $table->timestamps();

            $table->unique(['match_game_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_rally_events');
    }
};
