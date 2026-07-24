<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "One payment, multiple sessions" wrapper. A checkout that produces
     * more than one contiguous group of slots (e.g. 1-2pm + 5-6pm) creates
     * one of these plus one Booking per group (see bookings.booking_order_id)
     * - the individual Bookings still carry their own receipt/checkin
     * tokens and are what check-in/day-schedule/etc. operate on; this table
     * only exists to unify the payment step and the admin approve/reject
     * action across them. Mirrors the payment-related columns on `bookings`.
     */
    public function up(): void
    {
        Schema::create('booking_orders', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_token')->unique();

            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();

            $table->decimal('total_price', 8, 2);
            $table->string('status')->default('pending_payment');
            // pending_payment|confirmed|rejected|cancelled

            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gcash_reference')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->dateTime('gcash_submitted_at')->nullable();
            $table->foreignId('payment_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('payment_reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_orders');
    }
};
