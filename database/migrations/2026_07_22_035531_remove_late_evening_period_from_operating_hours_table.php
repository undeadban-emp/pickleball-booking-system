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
        // Late evening is being removed - Evening's end becomes the new
        // closing time (the venue's booking window shrinks accordingly).
        DB::table('operating_hours')->get()->each(function ($row) {
            DB::table('operating_hours')->where('id', $row->id)->update([
                'close_time' => $row->period_evening_end,
            ]);
        });

        Schema::table('operating_hours', function (Blueprint $table) {
            $table->dropColumn(['period_late_evening_start', 'period_late_evening_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operating_hours', function (Blueprint $table) {
            $table->time('period_late_evening_start')->default('00:00:00')->after('period_evening_end');
            $table->time('period_late_evening_end')->default('06:00:00')->after('period_late_evening_start');
        });
    }
};
