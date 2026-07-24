<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('open_play_players', function (Blueprint $table) {
            // Distinguishes "joined the room online" from "physically here
            // and eligible for matchmaking" - see OpenPlayMatchmakingService::eligiblePool().
            $table->dateTime('checked_in_at')->nullable()->after('available_since');
        });
    }

    public function down(): void
    {
        Schema::table('open_play_players', function (Blueprint $table) {
            $table->dropColumn('checked_in_at');
        });
    }
};
