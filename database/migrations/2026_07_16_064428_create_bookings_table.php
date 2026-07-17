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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending_payment');
            // pending_payment|confirmed|rejected|cancelled|completed|no_show
            $table->decimal('total_price', 8, 2);

            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gcash_reference')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->dateTime('gcash_submitted_at')->nullable();
            $table->foreignId('payment_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('payment_reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->string('checkin_token')->nullable()->unique();
            $table->dateTime('checkin_token_expires_at')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('receipt_token')->nullable()->unique();

            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
