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
        Schema::table('bookings', function (Blueprint $table) {
            // Self-referencing: which earlier booking (if any) this one was
            // created from via the admin "Rebook this customer" flow.
            $table->foreignId('rebooked_from_id')->nullable()->after('court_id')->constrained('bookings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rebooked_from_id');
        });
    }
};
