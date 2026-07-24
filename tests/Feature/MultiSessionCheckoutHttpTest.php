<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingOrder;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiSessionCheckoutHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_checkout_with_non_contiguous_slots_lands_on_the_order_page_and_admin_can_approve_it_in_one_action(): void
    {
        $court = Court::factory()->create();
        $date = now()->addDay()->toDateString();

        $slotA = CourtSlot::factory()->for($court)->create(['slot_date' => $date, 'start_time' => '13:00:00', 'end_time' => '14:00:00']);
        $slotB = CourtSlot::factory()->for($court)->create(['slot_date' => $date, 'start_time' => '17:00:00', 'end_time' => '18:00:00']);

        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->post(route('book.store', $court), [
            'court_slot_ids' => [$slotA->id, $slotB->id],
        ]);

        $order = BookingOrder::first();
        $this->assertNotNull($order, 'Expected a BookingOrder to have been created for the non-contiguous selection.');
        $this->assertSame(2, $order->bookings()->count());

        $response->assertRedirect(route('order.public', $order->receipt_token));

        // The order's public payment page renders.
        $this->get(route('order.public', $order->receipt_token))->assertOk();

        $paymentMethod = \App\Models\PaymentMethod::create([
            'name' => 'GCash',
            'account_number' => '0917 000 0000',
            'account_name' => 'Kitchen Line',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Submit one combined payment reference.
        $this->post(route('order.public.gcash-reference', $order->receipt_token), [
            'payment_method_id' => $paymentMethod->id,
            'gcash_reference' => 'REF999888',
        ])->assertRedirect(route('order.public', $order->receipt_token));

        $order->refresh();
        $this->assertSame('REF999888', $order->gcash_reference);
        $order->bookings->each(fn (Booking $b) => $this->assertSame('REF999888', $b->fresh()->gcash_reference));

        // Admin approves ONE of the underlying bookings - both should confirm.
        $admin = User::factory()->create(['role' => 'admin']);
        $firstBooking = $order->bookings->first();

        $this->actingAs($admin)
            ->post(route('admin.bookings.approve', $firstBooking))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $order->bookings->each(fn (Booking $b) => $this->assertSame('confirmed', $b->fresh()->status));
    }

    public function test_customer_checkout_with_a_single_contiguous_selection_still_lands_on_the_plain_booking_receipt(): void
    {
        $court = Court::factory()->create();
        $date = now()->addDay()->toDateString();

        $slotA = CourtSlot::factory()->for($court)->create(['slot_date' => $date, 'start_time' => '13:00:00', 'end_time' => '14:00:00']);
        $slotB = CourtSlot::factory()->for($court)->create(['slot_date' => $date, 'start_time' => '14:00:00', 'end_time' => '15:00:00']);

        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->post(route('book.store', $court), [
            'court_slot_ids' => [$slotA->id, $slotB->id],
        ]);

        $this->assertSame(0, BookingOrder::count());
        $booking = Booking::firstOrFail();
        $response->assertRedirect(route('booking.public', $booking->receipt_token));
    }
}
