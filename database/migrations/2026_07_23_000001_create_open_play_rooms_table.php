<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_play_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->date('session_date');
            $table->time('start_time');
            $table->string('skill_level')->default('any');
            // beginner|intermediate|advanced|any - self-declared label, not fed into matchmaking

            $table->unsignedSmallInteger('max_players');
            $table->string('match_format')->default('first_to');
            // first_to|timed
            $table->unsignedTinyInteger('points_target')->nullable();
            $table->unsignedTinyInteger('timer_minutes')->nullable();

            $table->string('visibility')->default('public');
            // public|private
            $table->string('join_code')->nullable();

            $table->string('status')->default('waiting');
            // waiting|ready|in_progress|finished|cancelled
            $table->unsignedInteger('current_round_number')->default(0);

            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_rooms');
    }
};
