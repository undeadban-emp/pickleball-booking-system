<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named "matches" (not "rounds") because one round spans several
        // simultaneous courts - this row is the court+round unit. Deliberately
        // not the existing `matches`/GameMatch table: this is a lightweight
        // win/loss record, not rally-scored, and self-serve, not admin/staff-only.
        Schema::create('open_play_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_play_room_id')->constrained('open_play_rooms')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();

            $table->unsignedInteger('round_number');

            $table->string('status')->default('scheduled');
            // scheduled|in_progress|completed|cancelled
            $table->unsignedTinyInteger('winner_team')->nullable();
            // 1 or 2

            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_matches');
    }
};
