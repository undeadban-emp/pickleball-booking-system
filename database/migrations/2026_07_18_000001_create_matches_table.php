<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

            $table->string('match_type')->default('doubles');
            // singles|doubles

            $table->unsignedTinyInteger('best_of')->default(3);
            $table->unsignedTinyInteger('games_to')->default(11);

            $table->string('win_rule')->default('win_by_2');
            // first_to|win_by_1|win_by_2

            $table->unsignedTinyInteger('timeouts_per_game')->default(2);

            $table->string('scoring_type')->default('service');
            // rally|service

            $table->unsignedTinyInteger('serving_team_first')->default(1); // 1 or 2

            $table->string('event_name')->nullable();
            $table->string('referee_name')->nullable();
            $table->string('location')->nullable();
            $table->string('email_results_to')->nullable();

            $table->string('status')->default('setup');
            // setup|verifying|in_progress|completed|forfeited

            $table->unsignedTinyInteger('winner_team')->nullable(); // 1 or 2, set on completion

            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
