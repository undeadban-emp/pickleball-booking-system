<?php

namespace Tests\Feature;

use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Models\BookingOrder;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\OpenPlayRoomCourt;
use App\Models\User;
use App\Services\BookingOrderService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingSplitRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected BookingService $bookings;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookings = app(BookingService::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
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

    protected function confirmedBooking(Court $court, array $slots): Booking
    {
        return $this->bookings->createConfirmedBooking(
            null, $court, collect($slots)->pluck('id')->all(), ['name' => 'Juan', 'phone' => '0900', 'email' => null], $this->admin
        );
    }

    public function test_splitting_the_middle_hour_creates_two_remainder_siblings(): void
    {
        $court = Court::factory()->create();
        $date = now()->addDay()->toDateString();
        $slots = $this->hourlySlots($court, $date, ['08:00:00', '09:00:00', '10:00:00']);
        $booking = $this->confirmedBooking($court, $slots);
        $originalTotal = (float) $booking->total_price;

        $destCourt = Court::factory()->create();
        $destDate = now()->addDays(3)->toDateString();
        $destSlot = $this->hourlySlots($destCourt, $destDate, ['15:00:00'])[0];

        $result = $this->bookings->splitAndReschedule($booking, [$slots[1]->id], $destCourt, [$destSlot->id], $this->admin, 'Rain');

        $moved = $result['moved'];
        $remainder = $result['remainder'];

        $this->assertSame($booking->id, $moved->id);
        $this->assertSame($booking->booking_code, $moved->booking_code);
        $this->assertCount(1, $moved->slots);
        $this->assertSame($destSlot->id, $moved->slots->first()->id);
        $this->assertEquals((float) $destSlot->price, (float) $moved->total_price);

        $this->assertCount(2, $remainder);
        $remainderSlotIds = $remainder->flatMap(fn (Booking $b) => $b->slots->pluck('id'))->sort()->values()->all();
        $this->assertEquals([$slots[0]->id, $slots[2]->id], $remainderSlotIds);

        $remainder->each(function (Booking $sibling) use ($booking) {
            $this->assertSame($booking->id, $sibling->split_from_booking_id);
            $this->assertSame('confirmed', $sibling->status);
            $this->assertNotNull($sibling->checkin_token);
            $this->assertSame($sibling->slots->count(), 1);
        });

        // Old slots freed, new slot booked.
        $this->assertSame('booked', $slots[0]->fresh()->status);
        $this->assertSame('booked', $slots[2]->fresh()->status);
        $this->assertSame('available', $slots[1]->fresh()->status);
        $this->assertSame('booked', $destSlot->fresh()->status);

        // All pieces share one order, total unchanged.
        $order = $moved->bookingOrder;
        $this->assertNotNull($order);
        $this->assertSame(3, $order->bookings()->count());
        $this->assertEquals($originalTotal, (float) $order->total_price);
    }

    public function test_splitting_an_edge_hour_leaves_only_one_remainder_group(): void
    {
        $court = Court::factory()->create();
        $date = now()->addDay()->toDateString();
        $slots = $this->hourlySlots($court, $date, ['08:00:00', '09:00:00', '10:00:00']);
        $booking = $this->confirmedBooking($court, $slots);

        $destSlot = $this->hourlySlots($court, now()->addDays(2)->toDateString(), ['12:00:00'])[0];

        $result = $this->bookings->splitAndReschedule($booking, [$slots[0]->id], $court, [$destSlot->id], $this->admin);

        $this->assertCount(1, $result['remainder']);
        $remainderIds = $result['remainder']->first()->slots->pluck('id')->sort()->values()->all();
        $this->assertEquals([$slots[1]->id, $slots[2]->id], $remainderIds);
    }

    public function test_splitting_all_hours_is_rejected(): void
    {
        $court = Court::factory()->create();
        $slots = $this->hourlySlots($court, now()->addDay()->toDateString(), ['08:00:00', '09:00:00']);
        $booking = $this->confirmedBooking($court, $slots);
        $destSlot = $this->hourlySlots($court, now()->addDays(2)->toDateString(), ['12:00:00', '13:00:00']);

        $this->expectException(\InvalidArgumentException::class);

        $this->bookings->splitAndReschedule($booking, collect($slots)->pluck('id')->all(), $court, collect($destSlot)->pluck('id')->all(), $this->admin);
    }

    public function test_splitting_a_booking_already_in_a_multi_session_order_keeps_existing_siblings(): void
    {
        $court = Court::factory()->create();
        $date = now()->addDay()->toDateString();
        // Two separate sessions (gap between them) go through the order checkout path.
        $slotsA = $this->hourlySlots($court, $date, ['08:00:00', '09:00:00', '10:00:00']);
        $slotsB = $this->hourlySlots($court, $date, ['15:00:00']);

        $order = app(BookingOrderService::class)->checkout(
            null, $court, collect([...$slotsA, ...$slotsB])->pluck('id')->all(), ['name' => 'Juan', 'phone' => '0900', 'email' => null]
        );
        $this->assertInstanceOf(BookingOrder::class, $order);

        $sessionA = $order->bookings->first(fn (Booking $b) => $b->slots->count() === 3);
        $sessionB = $order->bookings->first(fn (Booking $b) => $b->slots->count() === 1);
        $originalOrderTotal = (float) $order->total_price;

        $destSlot = $this->hourlySlots($court, now()->addDays(2)->toDateString(), ['12:00:00'])[0];

        $result = $this->bookings->splitAndReschedule($sessionA, [$slotsA[1]->id], $court, [$destSlot->id], $this->admin, 'Rain');

        $order->refresh();
        $this->assertSame(4, $order->bookings()->count()); // sessionA(moved) + sessionB(untouched) + 2 remainder siblings
        $this->assertTrue($order->bookings->contains('id', $sessionB->id));
        $this->assertEquals($originalOrderTotal, (float) $order->total_price);
    }

    public function test_split_with_active_open_play_room_is_blocked(): void
    {
        $court = Court::factory()->create();
        $slots = $this->hourlySlots($court, now()->addDay()->toDateString(), ['08:00:00', '09:00:00', '10:00:00']);
        $booking = $this->confirmedBooking($court, $slots);

        OpenPlayRoomCourt::factory()->create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
        ]);

        $destSlot = $this->hourlySlots($court, now()->addDays(2)->toDateString(), ['12:00:00'])[0];

        $this->expectException(InvalidBookingTransitionException::class);

        $this->bookings->splitAndReschedule($booking, [$slots[1]->id], $court, [$destSlot->id], $this->admin);
    }

    public function test_dashboard_sales_total_is_unchanged_by_a_split(): void
    {
        $court = Court::factory()->create();
        $slots = $this->hourlySlots($court, today()->toDateString(), ['08:00:00', '09:00:00', '10:00:00']);
        $booking = $this->confirmedBooking($court, $slots);

        $salesBefore = Booking::whereIn('status', ['confirmed', 'completed'])
            ->whereNull('rescheduled_from_id')
            ->whereDate('payment_reviewed_at', today())
            ->sum('total_price');

        $destSlot = $this->hourlySlots($court, now()->addDays(2)->toDateString(), ['12:00:00'])[0];
        $this->bookings->splitAndReschedule($booking, [$slots[1]->id], $court, [$destSlot->id], $this->admin);

        $salesAfter = Booking::whereIn('status', ['confirmed', 'completed'])
            ->whereNull('rescheduled_from_id')
            ->whereDate('payment_reviewed_at', today())
            ->sum('total_price');

        $this->assertEquals((float) $salesBefore, (float) $salesAfter);
    }

    public function test_representative_bookings_still_resolve_to_the_moved_booking(): void
    {
        $court = Court::factory()->create();
        $slots = $this->hourlySlots($court, now()->addDay()->toDateString(), ['08:00:00', '09:00:00', '10:00:00']);
        $booking = $this->confirmedBooking($court, $slots);
        $destSlot = $this->hourlySlots($court, now()->addDays(2)->toDateString(), ['12:00:00'])[0];

        $result = $this->bookings->splitAndReschedule($booking, [$slots[1]->id], $court, [$destSlot->id], $this->admin);

        $representativeId = Booking::where('booking_order_id', $result['moved']->booking_order_id)->min('id');

        $this->assertSame($booking->id, $representativeId);
    }
}
