<?php

namespace Tests\Feature;

use App\Exceptions\OpenPlayAuthorizationException;
use App\Exceptions\OpenPlayValidationException;
use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Services\OpenPlayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenPlayServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OpenPlayService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OpenPlayService::class);
    }

    protected function confirmedBooking(User $user, ?Court $court = null): Booking
    {
        $court = $court ?? Court::factory()->create();
        $slot = CourtSlot::factory()->for($court)->create();

        $booking = Booking::factory()->for($user)->for($court)->confirmed()->create();
        $booking->slots()->attach($slot->id);

        return $booking->fresh(['slots']);
    }

    protected function checkIn(\App\Models\OpenPlayRoom $room, User $user, ?User $actor = null): void
    {
        $player = $room->players()->where('user_id', $user->id)->firstOrFail();
        $this->service->checkIn($room, $player, $actor ?? $user);
    }

    public function test_create_room_rejects_booking_not_owned_by_host(): void
    {
        $owner = User::factory()->create();
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($owner);

        $this->expectException(OpenPlayAuthorizationException::class);

        $this->service->createRoom($host, ['title' => 'Test', 'max_players' => 8], [$booking->id]);
    }

    public function test_create_room_rejects_booking_that_is_not_confirmed(): void
    {
        $host = User::factory()->create();
        $court = Court::factory()->create();
        $slot = CourtSlot::factory()->for($court)->create();
        $booking = Booking::factory()->for($host)->for($court)->pendingPayment()->create();
        $booking->slots()->attach($slot->id);

        $this->expectException(OpenPlayValidationException::class);

        $this->service->createRoom($host, ['title' => 'Test', 'max_players' => 8], [$booking->id]);
    }

    public function test_create_room_derives_session_date_and_start_time_from_booking(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);
        $slot = $booking->slots->first();

        $room = $this->service->createRoom($host, ['title' => 'Friday Open Play', 'max_players' => 8], [$booking->id]);

        $this->assertSame($host->id, $room->host_user_id);
        $this->assertSame($slot->slot_date->toDateString(), $room->session_date->toDateString());
        $this->assertSame($slot->start_time, $room->start_time);
        $this->assertSame(1, $room->roomCourts->count());
        $this->assertSame($booking->id, $room->roomCourts->first()->booking_id);

        // The host plays too, not just organizes - they're seated as a
        // player the moment the room is created (but not auto-checked-in;
        // creating the room ahead of time doesn't mean they've arrived).
        $hostPlayer = $room->players->firstWhere('user_id', $host->id);
        $this->assertNotNull($hostPlayer);
        $this->assertFalse($hostPlayer->isCheckedIn());
    }

    public function test_create_room_rejects_reusing_a_booking_already_linked_to_a_room(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);

        $this->service->createRoom($host, ['title' => 'First room', 'max_players' => 8], [$booking->id]);

        $this->expectException(OpenPlayValidationException::class);

        $this->service->createRoom($host, ['title' => 'Second room', 'max_players' => 8], [$booking->id]);
    }

    public function test_join_room_enforces_max_players(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);
        // max_players=2: the host already occupies one slot (they play too),
        // so only one more player should be able to join.
        $room = $this->service->createRoom($host, ['title' => 'Test', 'max_players' => 2], [$booking->id]);

        $first = User::factory()->create();
        $this->service->joinRoom($room, $first);

        $second = User::factory()->create();

        $this->expectException(OpenPlayValidationException::class);

        $this->service->joinRoom($room, $second);
    }

    public function test_join_room_enforces_join_code_for_private_rooms(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);
        $room = $this->service->createRoom($host, [
            'title' => 'Private room',
            'max_players' => 8,
            'visibility' => 'private',
            'join_code' => 'SECRET',
        ], [$booking->id]);

        $joiner = User::factory()->create();

        $this->expectException(OpenPlayValidationException::class);

        $this->service->joinRoom($room, $joiner, 'WRONG');
    }

    public function test_check_in_requires_self_or_host(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);
        $room = $this->service->createRoom($host, ['title' => 'Test', 'max_players' => 8], [$booking->id]);

        $player = User::factory()->create();
        $this->service->joinRoom($room, $player);
        $playerRow = $room->players()->where('user_id', $player->id)->firstOrFail();

        $bystander = User::factory()->create();

        $this->expectException(OpenPlayAuthorizationException::class);

        $this->service->checkIn($room, $playerRow, $bystander);
    }

    public function test_check_in_is_idempotent_error_not_silent(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);
        $room = $this->service->createRoom($host, ['title' => 'Test', 'max_players' => 8], [$booking->id]);

        $this->checkIn($room, $host);

        $this->expectException(OpenPlayValidationException::class);

        $this->checkIn($room, $host);
    }

    public function test_start_session_requires_at_least_four_checked_in_players(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);
        $room = $this->service->createRoom($host, ['title' => 'Test', 'max_players' => 8], [$booking->id]);

        $others = User::factory()->count(3)->create();
        foreach ($others as $user) {
            $this->service->joinRoom($room, $user);
        }

        // 4 players have joined (host + 3), but only 2 are checked in -
        // joined count alone must not be enough to start.
        $this->checkIn($room, $host);
        $this->checkIn($room, $others[0]);

        $this->expectException(OpenPlayValidationException::class);
        $this->service->startSession($room, $host);
    }

    public function test_complete_match_updates_player_stats_and_rank(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);
        $room = $this->service->createRoom($host, ['title' => 'Test', 'max_players' => 8], [$booking->id]);

        $others = User::factory()->count(4)->create();
        foreach ($others as $user) {
            $this->service->joinRoom($room, $user);
        }

        $this->checkIn($room, $host);
        foreach ($others as $user) {
            $this->checkIn($room, $user);
        }

        $room = $this->service->startSession($room, $host);
        $match = $room->matches->first();
        $this->assertNotNull($match);

        $winningUserId = $match->matchPlayers()->where('team', 1)->first()->user_id;

        $this->service->completeMatch($match, 1, $host);

        $stat = \App\Models\OpenPlayPlayerStat::where('user_id', $winningUserId)->first();

        $this->assertSame(1, $stat->total_games);
        $this->assertSame(1, $stat->total_wins);
        $this->assertSame('Novice', $stat->rank);
    }

    public function test_check_in_after_session_started_does_not_throw(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);
        $room = $this->service->createRoom($host, ['title' => 'Test', 'max_players' => 8], [$booking->id]);

        // 5 players join before the room locks (only joining is restricted
        // to status=waiting - checking in is not), but only 4 check in
        // before the host starts the session.
        $others = User::factory()->count(4)->create();
        foreach ($others as $user) {
            $this->service->joinRoom($room, $user);
        }

        $this->checkIn($room, $host);
        foreach ($others->take(3) as $user) {
            $this->checkIn($room, $user);
        }

        $room = $this->service->startSession($room, $host);
        $this->assertCount(1, $room->matches);

        // The 5th joined-but-not-yet-checked-in player arrives late and
        // checks in after the session already started - checkIn() should
        // not throw even though the room is now in_progress (it triggers a
        // generateRound() pass to try to backfill a free court).
        $latecomer = $others->last();
        $this->checkIn($room, $latecomer);

        $this->assertTrue(
            $room->fresh()->players()->where('user_id', $latecomer->id)->first()->isCheckedIn()
        );
    }

    protected function startedRoomWithNoActivitySince(\Carbon\Carbon $lastActivity): \App\Models\OpenPlayRoom
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);
        $room = $this->service->createRoom($host, ['title' => 'Test', 'max_players' => 8], [$booking->id]);

        $others = User::factory()->count(3)->create();
        foreach ($others as $user) {
            $this->service->joinRoom($room, $user);
        }

        $this->checkIn($room, $host);
        foreach ($others as $user) {
            $this->checkIn($room, $user);
        }

        $room = $this->service->startSession($room, $host);
        $room->update(['last_activity_at' => $lastActivity]);

        return $room;
    }

    public function test_auto_end_stale_sessions_ends_rooms_inactive_past_the_window(): void
    {
        $room = $this->startedRoomWithNoActivitySince(now()->subHours(13));

        $count = $this->service->autoEndStaleSessions(12);

        $this->assertSame(1, $count);
        $room->refresh();
        $this->assertSame('finished', $room->status);
        $this->assertNotNull($room->ended_at);
        $this->assertSame(0, $room->matches()->where('status', 'in_progress')->count());
    }

    public function test_auto_end_stale_sessions_leaves_recently_active_rooms_alone(): void
    {
        $room = $this->startedRoomWithNoActivitySince(now()->subHours(2));

        $count = $this->service->autoEndStaleSessions(12);

        $this->assertSame(0, $count);
        $this->assertSame('in_progress', $room->fresh()->status);
    }

    public function test_auto_end_stale_sessions_ignores_rooms_not_in_progress(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);
        $room = $this->service->createRoom($host, ['title' => 'Test', 'max_players' => 8], [$booking->id]);

        // Still 'waiting' - never started, so last_activity_at is null and
        // it must never be swept up by the stale-session job.
        $count = $this->service->autoEndStaleSessions(12);

        $this->assertSame(0, $count);
        $this->assertSame('waiting', $room->fresh()->status);
    }
}
