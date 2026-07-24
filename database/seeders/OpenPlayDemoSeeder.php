<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\OpenPlayRoom;
use App\Models\User;
use App\Services\BookingService;
use App\Services\OpenPlayService;
use Illuminate\Database\Seeder;

/**
 * Demo data for the Open Play "waiting room" state: the demo customer
 * (player@kitchenline.app, from DatabaseSeeder) hosts a room from a
 * confirmed booking - and, like any host, is seated as a player in their
 * own room - plus 10 more players who've joined but haven't started the
 * session. Useful for eyeballing the join-list / "Joined" badge UI without
 * clicking through the flow by hand.
 *
 * Deletes and recreates its own room on every run, so re-seeding always
 * reflects the current OpenPlayService behavior instead of leaving stale
 * state from a previous run.
 *
 * Run with: php artisan db:seed --class=OpenPlayDemoSeeder
 */
class OpenPlayDemoSeeder extends Seeder
{
    protected const ROOM_TITLE = 'Demo Open Play — waiting for players';

    public function run(): void
    {
        $this->tearDownPreviousRun();

        $court = Court::first() ?? Court::factory()->create(['name' => 'Court 1']);

        $slot = CourtSlot::firstOrCreate(
            [
                'court_id' => $court->id,
                'slot_date' => now()->addDays(2)->toDateString(),
                'start_time' => '18:00:00',
            ],
            [
                'end_time' => '19:00:00',
                'price' => $court->default_price,
                'status' => 'available',
            ]
        );
        $slot->update(['status' => 'available']);

        $host = User::firstOrCreate(
            ['email' => 'player@kitchenline.app'],
            [
                'name' => 'Demo Player',
                'password' => bcrypt('password'),
                'role' => 'customer',
                'email_verified_at' => now(),
            ]
        );

        $admin = User::where('email', 'admin@kitchenline.app')->first() ?? $host;

        // Goes through the real booking + approve flow (not a hand-rolled
        // Booking::create()) so it gets everything a confirmed booking is
        // supposed to have - checkin_token, checkin_token_expires_at,
        // payment_reviewed_by/at, a status log entry - instead of silently
        // missing fields the rest of the app assumes are always present on
        // a status='confirmed' row (e.g. the receipt page's check-in QR).
        $booking = app(BookingService::class)->createConfirmedBooking(
            $host,
            $court,
            [$slot->id],
            null,
            $admin,
        );

        $service = app(OpenPlayService::class);

        // createRoom() seats the host as a player automatically - the host
        // plays too, not just organizes.
        $room = $service->createRoom($host, [
            'title' => self::ROOM_TITLE,
            'max_players' => 16,
            'skill_level' => 'any',
            'match_format' => 'first_to',
            'visibility' => 'public',
        ], [$booking->id]);

        // Check in the host plus the first 5 of 10 players, so the seeded
        // room demonstrates the "checked in" vs "joined but not arrived yet"
        // distinction instead of leaving it untested by the only available
        // demo dataset.
        $hostPlayer = $room->players()->where('user_id', $host->id)->firstOrFail();
        $service->checkIn($room, $hostPlayer, $host);

        collect(range(1, 10))->each(function (int $i) use ($service, $room) {
            $player = User::updateOrCreate(
                ['email' => "openplay.player{$i}@kitchenline.app"],
                [
                    'name' => "Open Play Player {$i}",
                    'password' => bcrypt('password'),
                    'role' => 'customer',
                    'email_verified_at' => now(),
                ]
            );

            $playerRow = $service->joinRoom($room->fresh(), $player);

            if ($i <= 5) {
                $service->checkIn($room->fresh(), $playerRow, $player);
            }
        });

        $checkedIn = $room->fresh()->players()->whereNotNull('checked_in_at')->count();
        $this->command?->info("Open Play demo room #{$room->id} ready with 11 players waiting (host + 10), {$checkedIn} checked in, host: {$host->email}");
    }

    /**
     * Remove whatever this seeder created last time it ran, so re-seeding
     * doesn't leave stale rooms/players/bookings behind or skip re-applying
     * behavior changes (e.g. the host now auto-joining as a player).
     */
    protected function tearDownPreviousRun(): void
    {
        $previousRooms = OpenPlayRoom::where('title', self::ROOM_TITLE)->with('roomCourts')->get();

        foreach ($previousRooms as $room) {
            $bookingIds = $room->roomCourts->pluck('booking_id');

            $room->delete(); // cascades room_courts/players/matches/match_players

            Booking::whereIn('id', $bookingIds)->get()->each(function (Booking $booking) {
                CourtSlot::whereIn('id', $booking->slots()->pluck('court_slots.id'))->update(['status' => 'available']);
                $booking->delete();
            });
        }
    }
}
