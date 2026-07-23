<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('operating_hours', function (Blueprint $table) {
            $table->time('period_morning_end')->default('12:00:00')->after('period_morning_start');
            $table->time('period_afternoon_end')->default('17:00:00')->after('period_afternoon_start');
            $table->time('period_evening_end')->default('00:00:00')->after('period_evening_start');
            $table->time('period_late_evening_end')->default('06:00:00')->after('period_late_evening_start');
        });

        // Backfill sensible defaults from the old "end = next period's start"
        // chain (and close_time for the last period) so existing settings
        // don't show blank end times the first time this page loads.
        DB::table('operating_hours')->get()->each(function ($row) {
            DB::table('operating_hours')->where('id', $row->id)->update([
                'period_morning_end' => $row->period_afternoon_start,
                'period_afternoon_end' => $row->period_evening_start,
                'period_evening_end' => $row->period_late_evening_start,
                'period_late_evening_end' => $row->close_time,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operating_hours', function (Blueprint $table) {
            $table->dropColumn([
                'period_morning_end',
                'period_afternoon_end',
                'period_evening_end',
                'period_late_evening_end',
            ]);
        });
    }
};
