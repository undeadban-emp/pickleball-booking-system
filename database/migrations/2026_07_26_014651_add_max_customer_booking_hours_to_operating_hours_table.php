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
            // How many hours a customer (guest or logged-in) can select in a
            // single booking submission - staff walk-in bookings have no
            // such cap, this only governs the public self-service flow.
            $table->unsignedInteger('max_customer_booking_hours')->nullable()->default(24);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operating_hours', function (Blueprint $table) {
            $table->dropColumn('max_customer_booking_hours');
        });
    }
};
