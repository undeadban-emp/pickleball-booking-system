<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingOrder;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Services\BookingOrderService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BookingOrderService $checkout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkout = app(BookingOrderService::class);
    }

    protected function hourlySlots(Court $court, string $date, array $startTimes): array
    {
        return collect($startTimes)->map(function (string $start) use ($court, $date) {
            [$h] = explode(':', $start);
            $end = sprintf('%02d:00:00', ((int) $h) + 1);

            return CourtSlot::factory()->for($court)->create([
                'slot_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
            ]);
        })->all();
    }

    public function test_group_contiguous_slot_ids_splits_on_gaps_and_date_changes(): void
    {
        $court = Court::factory()->create();
        $today = now()->addDay()->toDateString();
        $tomorrow = now()->addDays(2)->toDateString();

        $todaySlots = $this->hourlySlots($court, $today, ['13:00:00', '14:00:00', '17:00:00']);
        $tomorrowSlot = $this->hourlySlots($court, $tomorrow, ['13:00:00'])[0];

        $ids = collect($todaySlots)->pluck('id')->push($tomorrowSlot->id)->all();

        $groups = app(BookingService::class)->groupContiguousSlotIds($ids);

        $this->assertCount(3, $groups);
        $this->assertEquals([$todaySlots[0]->id, $todaySlots[1]->id], $groups[0]);
        $this->assertEquals([$todaySlots[2]->id], $groups[1]);
        $this->assertEquals([$tomorrowSlot->id], $groups[2]);
    }

    public function test_a_single_contiguous_selection_returns_a_plain_booking_no_order(): void
    {
        $court = Court::factory()->create();
        $slots = $this->hourlySlots($court, now()->addDay()->toDateString(), ['13:00:00', '14:00:00']);
        $guest = ['name' => 'Juan', 'phone' => '0900', 'email' => null];

        $result = $this->checkout->checkout(null, $court, collect($slots)->pluck('id')->all(), $guest);

        $this->assertInstanceOf(Booking::class, $result);
        $this->assertNull($result->booking_order_id);
        $this->assertSame(0, BookingOrder::count());
    }

    public function test_a_non_contiguous_selection_creates_an_order_with_one_booking_per_group(): void
    {
        $court = Court::factory()->create();
        $date = now()->addDay()->toDateString();
        // 1-2pm and 5-6pm, skipping the gap - two separate sessions.
        $slots = $this->hourlySlots($court, $date, ['13:00:00', '17:00:00']);
        $guest = ['name' => 'Juan', 'phone' => '0900', 'email' => null];

        $result = $this->checkout->checkout(null, $court, collect($slots)->pluck('id')->all(), $guest);

        $this->assertInstanceOf(BookingOrder::class, $result);
        $this->assertSame(2, $result->bookings->count());
        $this->assertSame((float) $slots[0]->price + (float) $slots[1]->price, (float) $result->total_price);

        $result->bookings->each(function (Booking $booking) use ($result) {
            $this->assertSame($result->id, $booking->booking_order_id);
            $this->assertSame('pending_payment', $booking->status);
        });
    }

    public function test_submit_gcash_reference_mirrors_onto_every_child_booking(): void
    {
        $court = Court::factory()->create();
        $date = now()->addDay()->toDateString();
        $slots = $this->hourlySlots($court, $date, ['13:00:00', '17:00:00']);

        $order = $this->checkout->checkout(null, $court, collect($slots)->pluck('id')->all(), ['name' => 'Juan', 'phone' => '0900', 'email' => null]);

        $this->checkout->submitGcashReference($order, 'REF123456', null, null);

        $order->refresh();
        $this->assertSame('REF123456', $order->gcash_reference);

        $order->bookings->each(function (Booking $booking) {
            $this->assertSame('REF123456', $booking->fresh()->gcash_reference);
        });
    }

    public function test_approving_an_order_confirms_every_child_booking_together(): void
    {
        $court = Court::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $date = now()->addDay()->toDateString();
        $slots = $this->hourlySlots($court, $date, ['13:00:00', '17:00:00']);

        $order = $this->checkout->checkout(null, $court, collect($slots)->pluck('id')->all(), ['name' => 'Juan', 'phone' => '0900', 'email' => null]);
        $this->checkout->submitGcashReference($order, 'REF123456', null, null);

        $order = $this->checkout->approve($order, $admin);

        $this->assertSame('confirmed', $order->status);
        $order->bookings->each(function (Booking $booking) {
            $this->assertSame('confirmed', $booking->fresh()->status);
            $this->assertNotNull($booking->fresh()->checkin_token);
        });
    }

    public function test_a_session_already_taken_is_skipped_but_the_rest_of_the_order_still_gets_created(): void
    {
        $court = Court::factory()->create();
        $date = now()->addDay()->toDateString();
        $slots = $this->hourlySlots($court, $date, ['13:00:00', '17:00:00']);

        // Someone else already booked the second group's slot.
        $slots[1]->update(['status' => 'booked']);

        $order = $this->checkout->checkout(null, $court, collect($slots)->pluck('id')->all(), ['name' => 'Juan', 'phone' => '0900', 'email' => null]);

        $this->assertInstanceOf(BookingOrder::class, $order);
        $this->assertSame(1, $order->bookings->count());
        $this->assertSame((float) $slots[0]->price, (float) $order->total_price);
    }

    public function test_cancelling_an_order_releases_every_session_slot(): void
    {
        $court = Court::factory()->create();
        $date = now()->addDay()->toDateString();
        $slots = $this->hourlySlots($court, $date, ['13:00:00', '17:00:00']);

        $order = $this->checkout->checkout(null, $court, collect($slots)->pluck('id')->all(), ['name' => 'Juan', 'phone' => '0900', 'email' => null]);

        $this->checkout->cancel($order, null, 'Changed my mind');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $order->bookings->each(fn (Booking $b) => $this->assertSame('cancelled', $b->fresh()->status));

        foreach ($slots as $slot) {
            $this->assertSame('available', $slot->fresh()->status);
        }
    }
}
