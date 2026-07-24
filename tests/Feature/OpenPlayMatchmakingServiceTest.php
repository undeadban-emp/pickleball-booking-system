<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\OpenPlayMatchPlayer;
use App\Models\OpenPlayPlayer;
use App\Models\OpenPlayRoom;
use App\Models\OpenPlayRoomCourt;
use App\Services\OpenPlayMatchmakingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenPlayMatchmakingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OpenPlayMatchmakingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OpenPlayMatchmakingService::class);
    }

    public function test_generate_round_fills_free_courts_and_avoids_repeat_partners_and_opponents_across_rounds(): void
    {
        $room = OpenPlayRoom::factory()->create(['current_round_number' => 0]);

        // Two courts, so each round produces two matches (8 players).
        OpenPlayRoomCourt::factory()->for($room, 'room')->create(['court_id' => Court::factory()->create()->id]);
        OpenPlayRoomCourt::factory()->for($room, 'room')->create(['court_id' => Court::factory()->create()->id]);

        OpenPlayPlayer::factory()->for($room, 'room')->count(8)->create([
            'available_since' => now()->subMinutes(10),
        ]);

        $seenPartners = [];
        $seenOpponents = [];

        for ($round = 1; $round <= 3; $round++) {
            $created = $this->service->generateRound($room->fresh());

            $this->assertCount(2, $created, "Round {$round} should fill both free courts.");

            foreach ($created as $match) {
                $teamA = $match->matchPlayers()->where('team', 1)->pluck('user_id')->sort()->values();
                $teamB = $match->matchPlayers()->where('team', 2)->pluck('user_id')->sort()->values();

                $this->assertCount(2, $teamA);
                $this->assertCount(2, $teamB);

                $partnerKeyA = $teamA->implode('-');
                $partnerKeyB = $teamB->implode('-');

                // With only 8 players and no repeats yet, the greedy matcher
                // should always find a fresh partner for the first couple of rounds.
                if ($round <= 2) {
                    $this->assertArrayNotHasKey($partnerKeyA, $seenPartners, 'Team A repeated a partner pairing too early.');
                    $this->assertArrayNotHasKey($partnerKeyB, $seenPartners, 'Team B repeated a partner pairing too early.');
                }

                $seenPartners[$partnerKeyA] = true;
                $seenPartners[$partnerKeyB] = true;

                foreach ($teamA as $a) {
                    foreach ($teamB as $b) {
                        $key = $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";

                        if ($round <= 2) {
                            $this->assertArrayNotHasKey($key, $seenOpponents, 'Repeated an opponent pairing too early.');
                        }

                        $seenOpponents[$key] = true;
                    }
                }

                // Simulate the match finishing so the next round has a full
                // waiting pool again.
                $match->update(['status' => 'completed', 'winner_team' => 1]);

                OpenPlayPlayer::whereIn('id', $match->matchPlayers()->pluck('open_play_player_id'))
                    ->update([
                        'current_status' => 'waiting',
                        'available_since' => now(),
                        'games_played' => \Illuminate\Support\Facades\DB::raw('games_played + 1'),
                    ]);
            }
        }
    }

    public function test_generate_round_prioritizes_longest_waiting_and_fewest_games_played(): void
    {
        $room = OpenPlayRoom::factory()->create();
        OpenPlayRoomCourt::factory()->for($room, 'room')->create();

        // 6 players, only 1 free court -> only 4 should be picked, the ones
        // that have waited longest / played the fewest games.
        $shouldPlay = OpenPlayPlayer::factory()->for($room, 'room')->count(4)->create([
            'available_since' => now()->subMinutes(30),
            'games_played' => 0,
        ]);

        $shouldWait = OpenPlayPlayer::factory()->for($room, 'room')->count(2)->create([
            'available_since' => now(),
            'games_played' => 5,
        ]);

        $created = $this->service->generateRound($room->fresh());

        $this->assertCount(1, $created);

        $pickedUserIds = OpenPlayMatchPlayer::where('open_play_match_id', $created->first()->id)
            ->pluck('user_id')
            ->sort()
            ->values();

        $expectedUserIds = $shouldPlay->pluck('user_id')->sort()->values();

        $this->assertSame($expectedUserIds->all(), $pickedUserIds->all());

        foreach ($shouldWait as $player) {
            $this->assertSame('waiting', $player->fresh()->current_status);
        }
    }
}
