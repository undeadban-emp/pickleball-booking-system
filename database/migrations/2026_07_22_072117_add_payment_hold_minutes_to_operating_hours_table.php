<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('operating_hours', function (Blueprint $table) {
            // How long a pending_payment booking holds its slot before it's
            // automatically released back to available. Null = never expire.
            $table->unsignedInteger('payment_hold_minutes')->nullable()->default(30);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operating_hours', function (Blueprint $table) {
            $table->dropColumn('payment_hold_minutes');
        });
    }
};
