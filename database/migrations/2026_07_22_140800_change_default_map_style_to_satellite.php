<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL-only syntax - sqlite (used for the test suite) doesn't
        // enforce column defaults strictly, so there's nothing to migrate
        // there; skipping keeps `php artisan test` working without pulling
        // in doctrine/dbal just for this one column-default tweak.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE operating_hours MODIFY map_style VARCHAR(255) NOT NULL DEFAULT 'satellite'");
        }

        // Nobody has deliberately picked a style yet on this brand-new
        // feature, so it's safe to flip the untouched default forward too.
        DB::table('operating_hours')->where('map_style', 'standard')->update(['map_style' => 'satellite']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE operating_hours MODIFY map_style VARCHAR(255) NOT NULL DEFAULT 'standard'");
        }
    }
};
