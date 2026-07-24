<?php

namespace App\Services;

use App\Models\OpenPlayMatch;
use App\Models\OpenPlayMatchPlayer;
use App\Models\OpenPlayPlayer;
use App\Models\OpenPlayRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OpenPlayMatchmakingService
{
    /**
     * Fill every free court in the room with a new match, drawn from the
     * waiting queue in strict priority order: longest-waiting, then fewest
     * games played, then a deterministic id tiebreak. Teams/opponents are
     * formed greedily to avoid repeats where possible. Returns the newly
     * created OpenPlayMatch rows (empty if there weren't enough free courts
     * or waiting players to form even one match).
     *
     * @return Collection<int, OpenPlayMatch>
     */
    public function generateRound(OpenPlayRoom $room): Collection
    {
        return DB::transaction(function () use ($room) {
            $freeCourtIds = $this->freeCourtIds($room);

            if ($freeCourtIds->isEmpty()) {
                return collect();
            }

            $pool = $this->eligiblePool($room, $freeCourtIds->count() * 4);

            if ($pool->count() < 4) {
                return collect();
            }

            $partnerHistory = $this->partnerHistory($room);
            $opponentHistory = $this->opponentHistory($room);

            $teams = $this->formTeams($pool, $partnerHistory);
            $matches = $this->pairTeamsIntoMatches($teams, $opponentHistory);

            if (empty($matches)) {
                return collect();
            }

            $created = collect();
            $roundNumber = $room->current_round_number + 1;

            foreach (array_values($matches) as $index => [$teamA, $teamB]) {
                if (! isset($freeCourtIds[$index])) {
                    break;
                }

                $match = OpenPlayMatch::create([
                    'open_play_room_id' => $room->id,
                    'court_id' => $freeCourtIds[$index],
                    'round_number' => $roundNumber,
                    'status' => 'in_progress',
                    'started_at' => now(),
                ]);

                $this->assignTeam($match, $teamA, 1);
                $this->assignTeam($match, $teamB, 2);

                $created->push($match);
            }

            if ($created->isNotEmpty()) {
                $room->update(['current_round_number' => $roundNumber]);
            }

            return $created;
        });
    }

    /**
     * @return Collection<int, int>
     */
    protected function freeCourtIds(OpenPlayRoom $room): Collection
    {
        $busyCourtIds = $room->matches()->where('status', 'in_progress')->pluck('court_id');

        return $room->roomCourts()->pluck('court_id')->diff($busyCourtIds)->values();
    }

    /**
     * @return Collection<int, OpenPlayPlayer>
     */
    protected function eligiblePool(OpenPlayRoom $room, int $limit): Collection
    {
        return OpenPlayPlayer::where('open_play_room_id', $room->id)
            ->where('current_status', 'waiting')
            ->whereNull('left_at')
            ->whereNotNull('checked_in_at')
            ->lockForUpdate()
            ->orderBy('available_since')
            ->orderBy('games_played')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Greedily pair the (already priority-sorted) pool into teams, avoiding
     * repeat partners where possible. Falls back to the least-repeated
     * pairing when every remaining candidate has already partnered with the
     * current player (small/long-running rooms can exhaust fresh pairings).
     *
     * @param  Collection<int, OpenPlayPlayer>  $pool
     * @param  array<string, int>  $partnerHistory
     * @return array<int, array{0: OpenPlayPlayer, 1: OpenPlayPlayer}>
     */
    protected function formTeams(Collection $pool, array $partnerHistory): array
    {
        $remaining = $pool->values()->all();
        $teams = [];

        while (count($remaining) >= 2) {
            $player = array_shift($remaining);
            $bestKey = null;
            $bestCount = null;

            foreach ($remaining as $key => $candidate) {
                $count = $partnerHistory[$this->pairKey($player->user_id, $candidate->user_id)] ?? 0;

                if ($count === 0) {
                    $bestKey = $key;
                    break;
                }

                if ($bestCount === null || $count < $bestCount) {
                    $bestCount = $count;
                    $bestKey = $key;
                }
            }

            if ($bestKey === null) {
                break;
            }

            $teams[] = [$player, $remaining[$bestKey]];
            unset($remaining[$bestKey]);
            $remaining = array_values($remaining);
        }

        return $teams;
    }

    /**
     * Greedily pair teams into matches, avoiding repeat opponents where
     * possible, using the same least-repeated fallback as formTeams().
     *
     * @param  array<int, array{0: OpenPlayPlayer, 1: OpenPlayPlayer}>  $teams
     * @param  array<string, int>  $opponentHistory
     * @return array<int, array{0: array{0: OpenPlayPlayer, 1: OpenPlayPlayer}, 1: array{0: OpenPlayPlayer, 1: OpenPlayPlayer}}>
     */
    protected function pairTeamsIntoMatches(array $teams, array $opponentHistory): array
    {
        $remaining = $teams;
        $matches = [];

        while (count($remaining) >= 2) {
            $teamA = array_shift($remaining);
            $bestKey = null;
            $bestCount = null;

            foreach ($remaining as $key => $teamB) {
                $count = $this->opponentPairCount($teamA, $teamB, $opponentHistory);

                if ($count === 0) {
                    $bestKey = $key;
                    break;
                }

                if ($bestCount === null || $count < $bestCount) {
                    $bestCount = $count;
                    $bestKey = $key;
                }
            }

            if ($bestKey === null) {
                break;
            }

            $matches[] = [$teamA, $remaining[$bestKey]];
            unset($remaining[$bestKey]);
            $remaining = array_values($remaining);
        }

        return $matches;
    }

    /**
     * @param  array{0: OpenPlayPlayer, 1: OpenPlayPlayer}  $teamA
     * @param  array{0: OpenPlayPlayer, 1: OpenPlayPlayer}  $teamB
     * @param  array<string, int>  $opponentHistory
     */
    protected function opponentPairCount(array $teamA, array $teamB, array $opponentHistory): int
    {
        $count = 0;

        foreach ($teamA as $a) {
            foreach ($teamB as $b) {
                $count += $opponentHistory[$this->pairKey($a->user_id, $b->user_id)] ?? 0;
            }
        }

        return $count;
    }

    /**
     * @return array<string, int> map of "smallerId-largerId" => times paired as teammates
     */
    protected function partnerHistory(OpenPlayRoom $room): array
    {
        return $this->pairHistory($room, sameTeam: true);
    }

    /**
     * @return array<string, int> map of "smallerId-largerId" => times faced as opponents
     */
    protected function opponentHistory(OpenPlayRoom $room): array
    {
        return $this->pairHistory($room, sameTeam: false);
    }

    /**
     * @return array<string, int>
     */
    protected function pairHistory(OpenPlayRoom $room, bool $sameTeam): array
    {
        $history = [];

        $grouped = OpenPlayMatchPlayer::whereHas('match', fn ($q) => $q->where('open_play_room_id', $room->id))
            ->get(['open_play_match_id', 'user_id', 'team'])
            ->groupBy('open_play_match_id');

        foreach ($grouped as $players) {
            if ($sameTeam) {
                foreach ([1, 2] as $team) {
                    $ids = $players->where('team', $team)->pluck('user_id')->values();

                    if ($ids->count() === 2) {
                        $key = $this->pairKey($ids[0], $ids[1]);
                        $history[$key] = ($history[$key] ?? 0) + 1;
                    }
                }
            } else {
                $teamA = $players->where('team', 1)->pluck('user_id');
                $teamB = $players->where('team', 2)->pluck('user_id');

                foreach ($teamA as $a) {
                    foreach ($teamB as $b) {
                        $key = $this->pairKey($a, $b);
                        $history[$key] = ($history[$key] ?? 0) + 1;
                    }
                }
            }
        }

        return $history;
    }

    protected function pairKey(int $a, int $b): string
    {
        return $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";
    }

    /**
     * @param  array{0: OpenPlayPlayer, 1: OpenPlayPlayer}  $team
     */
    protected function assignTeam(OpenPlayMatch $match, array $team, int $teamNumber): void
    {
        foreach ($team as $player) {
            OpenPlayMatchPlayer::create([
                'open_play_match_id' => $match->id,
                'open_play_player_id' => $player->id,
                'user_id' => $player->user_id,
                'team' => $teamNumber,
            ]);
        }

        OpenPlayPlayer::whereIn('id', array_map(fn ($p) => $p->id, $team))
            ->update(['current_status' => 'playing']);
    }
}
