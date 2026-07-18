<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();

            $table->unsignedTinyInteger('team'); // 1 or 2

            // Stable identity: this player's original slot within their team (1st or
            // 2nd one entered on the setup form). Never changes during play — this is
            // what "server_number" cross-references to know WHO is serving.
            $table->unsignedTinyInteger('slot');

            // Mutable: which court side this player is CURRENTLY standing in
            // (1 = right court, 2 = left court), scoped per team, not a global 1-4
            // quadrant id. Rotates every point the player's team wins while serving,
            // per MatchScoringService::applyServingArrangement(). Doubles only —
            // singles players stay put (only one player per side, nothing to swap).
            $table->unsignedTinyInteger('position');

            $table->string('name');

            $table->string('gender')->default('unknown');
            // unknown|f|m|x

            $table->timestamps();

            $table->unique(['match_id', 'team', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
