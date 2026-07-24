<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Services\OpenPlayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenPlayHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected OpenPlayService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OpenPlayService::class);
    }

    protected function confirmedBooking(User $user): Booking
    {
        $court = Court::factory()->create();
        $slot = CourtSlot::factory()->for($court)->create();

        $booking = Booking::factory()->for($user)->for($court)->confirmed()->create();
        $booking->slots()->attach($slot->id);

        return $booking->fresh(['slots']);
    }

    public function test_history_does_not_list_sessions_that_have_not_finished_yet(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);

        $room = $this->service->createRoom($host, ['title' => 'Still Waiting Room', 'max_players' => 8], [$booking->id]);

        $response = $this->actingAs($host)->get(route('open-play.history'));

        $response->assertOk();
        $response->assertDontSee('Still Waiting Room');
    }

    public function test_history_lists_sessions_once_the_host_ends_them(): void
    {
        $host = User::factory()->create();
        $booking = $this->confirmedBooking($host);

        $room = $this->service->createRoom($host, ['title' => 'Finished Room', 'max_players' => 8], [$booking->id]);

        $others = User::factory()->count(3)->create();
        foreach ($others as $user) {
            $this->service->joinRoom($room, $user);
        }

        $hostPlayer = $room->players()->where('user_id', $host->id)->firstOrFail();
        $this->service->checkIn($room, $hostPlayer, $host);
        foreach ($others as $user) {
            $row = $room->players()->where('user_id', $user->id)->firstOrFail();
            $this->service->checkIn($room, $row, $user);
        }

        $room = $this->service->startSession($room, $host);
        $this->service->endSession($room, $host);

        $response = $this->actingAs($host)->get(route('open-play.history'));

        $response->assertOk();
        $response->assertSee('Finished Room');
    }
}
