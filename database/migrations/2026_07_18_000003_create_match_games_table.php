<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();

            $table->unsignedTinyInteger('game_number');
            $table->unsignedSmallInteger('team1_score')->default(0);
            $table->unsignedSmallInteger('team2_score')->default(0);

            $table->unsignedTinyInteger('serving_team')->default(1);

            // Which team SLOT (cross-references match_players.slot, not a global
            // player id) is currently serving. Unaffected by Point — only changes on
            // Side Out. Doubles games start "0-0-2": the starting team gets only one
            // server before the first side-out, so the opening server is already
            // treated as the team's "second" server. See MatchScoringService.
            $table->unsignedTinyInteger('server_number')->default(2);

            // Current court side (1 = right, 2 = left) of whoever server_number
            // points to. Right/left ownership rotates every point the serving team
            // wins (real pickleball rule: server's side flips with each point,
            // tracking their own team's score parity) — see applyServingArrangement().
            $table->unsignedTinyInteger('server_position')->nullable();

            // Immutable snapshot of the three fields above as they were at game
            // start, kept alongside the live (mutable) columns so "undo to here"
            // (MatchScoringService::rewindGame) can reconstruct state at sequence 0
            // without replaying every event from scratch.
            $table->unsignedTinyInteger('starting_serving_team')->default(1);
            $table->unsignedTinyInteger('starting_server_position')->nullable();
            $table->unsignedTinyInteger('starting_server_number')->default(2);

            // Snapshot of every player's court side (id => 1|2) at game start, so a
            // rewind to sequence 0 can restore BOTH teams' arrangements exactly —
            // not just the serving team's, which the three columns above cover alone.
            $table->json('starting_player_positions')->nullable();

            $table->unsignedTinyInteger('team1_timeouts_used')->default(0);
            $table->unsignedTinyInteger('team2_timeouts_used')->default(0);

            $table->string('status')->default('in_progress');
            // in_progress|completed

            $table->unsignedTinyInteger('winner_team')->nullable();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['match_id', 'game_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_games');
    }
};
