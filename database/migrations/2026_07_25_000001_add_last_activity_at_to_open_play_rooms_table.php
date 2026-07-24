<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('open_play_rooms', function (Blueprint $table) {
            // Bumped on every host-driven action while the session is live
            // (start, match completion, check-in) - used to auto-end a
            // session nobody remembered to close. See
            // OpenPlayService::autoEndStaleSessions().
            $table->dateTime('last_activity_at')->nullable()->after('started_at');
        });

        // Backfill any room already in_progress before this column existed,
        // so it isn't stuck permanently ineligible for auto-end (a null
        // last_activity_at never matches the "<= cutoff" check). Every room
        // row always has updated_at (Eloquent-managed), so no NOW() fallback
        // is needed - keeps this portable across the MySQL/sqlite split the
        // app already runs on (dev vs. test).
        DB::table('open_play_rooms')
            ->where('status', 'in_progress')
            ->whereNull('last_activity_at')
            ->update(['last_activity_at' => DB::raw('COALESCE(updated_at, started_at)')]);
    }

    public function down(): void
    {
        Schema::table('open_play_rooms', function (Blueprint $table) {
            $table->dropColumn('last_activity_at');
        });
    }
};
