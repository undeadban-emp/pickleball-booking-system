<?php

namespace App\Services;

use App\Exceptions\OpenPlayAuthorizationException;
use App\Exceptions\OpenPlayValidationException;
use App\Models\Booking;
use App\Models\OpenPlayMatch;
use App\Models\OpenPlayPlayer;
use App\Models\OpenPlayPlayerStat;
use App\Models\OpenPlayRoom;
use App\Models\OpenPlayRoomCourt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OpenPlayService
{
    public function __construct(protected OpenPlayMatchmakingService $matchmaking) {}

    /**
     * Turn one or more of the host's own confirmed bookings into an Open
     * Play room. No new Booking is ever created here - Open Play only rides
     * on court time the host already paid for and had confirmed through the
     * normal booking flow.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int>  $bookingIds
     */
    public function createRoom(User $host, array $data, array $bookingIds): OpenPlayRoom
    {
        return DB::transaction(function () use ($host, $data, $bookingIds) {
            $bookings = Booking::with('slots')
                ->whereIn('id', $bookingIds)
                ->lockForUpdate()
                ->get();

            if ($bookings->count() !== count($bookingIds)) {
                throw new OpenPlayValidationException('One or more selected bookings could not be found.');
            }

            $firstSlots = $bookings->mapWithKeys(function (Booking $booking) {
                $firstSlot = $booking->slots->sortBy('start_time')->first();

                if (! $firstSlot) {
                    throw new OpenPlayValidationException('One of the selected bookings has no reserved time slot.');
                }

                return [$booking->id => $firstSlot];
            });

            $reference = $firstSlots->first();

            foreach ($bookings as $booking) {
                if ($booking->user_id !== $host->id) {
                    throw new OpenPlayAuthorizationException('You can only open a room for a court you have booked yourself.');
                }

                if ($booking->status !== 'confirmed') {
                    throw new OpenPlayValidationException('Only confirmed bookings can be turned into an Open Play room.');
                }

                if (OpenPlayRoomCourt::where('booking_id', $booking->id)->exists()) {
                    throw new OpenPlayValidationException('This booking has already been used to open an Open Play room.');
                }

                $slot = $firstSlots[$booking->id];

                if (
                    $slot->slot_date->toDateString() !== $reference->slot_date->toDateString()
                    || $slot->start_time !== $reference->start_time
                ) {
                    throw new OpenPlayValidationException('All selected bookings must be for the same date and start time.');
                }
            }

            $visibility = $data['visibility'] ?? 'public';

            $room = OpenPlayRoom::create([
                'host_user_id' => $host->id,
                'title' => $data['title'],
                'session_date' => $reference->slot_date,
                'start_time' => $reference->start_time,
                'skill_level' => $data['skill_level'] ?? 'any',
                'max_players' => $data['max_players'],
                'match_format' => $data['match_format'] ?? 'first_to',
                'points_target' => $data['points_target'] ?? null,
                'timer_minutes' => $data['timer_minutes'] ?? null,
                'visibility' => $visibility,
                'join_code' => $visibility === 'private' ? ($data['join_code'] ?? Str::upper(Str::random(6))) : null,
                'status' => 'waiting',
            ]);

            foreach ($bookings as $booking) {
                $room->roomCourts()->create([
                    'court_id' => $booking->court_id,
                    'booking_id' => $booking->id,
                ]);
            }

            // The host plays too, not just organizes - seat them as the
            // room's first player so they're eligible for matchmaking like
            // everyone else once the session starts.
            $room->players()->create([
                'user_id' => $host->id,
                'current_status' => 'waiting',
                'available_since' => now(),
            ]);

            return $room->fresh(['roomCourts.court', 'players']);
        });
    }

    public function joinRoom(OpenPlayRoom $room, User $user, ?string $joinCode = null): OpenPlayPlayer
    {
        return DB::transaction(function () use ($room, $user, $joinCode) {
            /** @var OpenPlayRoom $room */
            $room = OpenPlayRoom::whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($room->status !== 'waiting') {
                throw new OpenPlayValidationException('This room is no longer accepting players.');
            }

            if ($room->visibility === 'private' && $room->join_code !== $joinCode) {
                throw new OpenPlayValidationException('Invalid join code.');
            }

            if ($room->players()->where('user_id', $user->id)->exists()) {
                throw new OpenPlayValidationException('You have already joined this room.');
            }

            if ($room->players()->count() >= $room->max_players) {
                throw new OpenPlayValidationException('This room is full.');
            }

            return $room->players()->create([
                'user_id' => $user->id,
                'current_status' => 'waiting',
                'available_since' => now(),
            ]);
        });
    }

    public function leaveRoom(OpenPlayRoom $room, User $user): void
    {
        DB::transaction(function () use ($room, $user) {
            $player = $room->players()->where('user_id', $user->id)->lockForUpdate()->first();

            if (! $player) {
                throw new OpenPlayValidationException('You are not part of this room.');
            }

            if ($room->status !== 'waiting') {
                throw new OpenPlayValidationException('You can no longer leave once the session has started — ask the host to remove you.');
            }

            $player->delete();
        });
    }

    /**
     * Host-only removal of a player mid-session (voluntary drop-out relayed
     * through the host, or a no-show) - unlike leaveRoom(), this works while
     * the room is in_progress.
     */
    public function removePlayer(OpenPlayRoom $room, OpenPlayPlayer $player, User $host): void
    {
        if ($room->host_user_id !== $host->id) {
            throw new OpenPlayAuthorizationException('Only the host can remove a player.');
        }

        $player->update(['current_status' => 'left', 'left_at' => now()]);
        $room->update(['last_activity_at' => now()]);
    }

    /**
     * Marks a player as physically present, distinct from having merely
     * joined the room online - only checked-in players are eligible for
     * matchmaking (see OpenPlayMatchmakingService::eligiblePool()). Either
     * the player themself or the host (checking someone in on their behalf)
     * may call this.
     */
    public function checkIn(OpenPlayRoom $room, OpenPlayPlayer $player, User $actor): OpenPlayPlayer
    {
        return DB::transaction(function () use ($room, $player, $actor) {
            /** @var OpenPlayPlayer $player */
            $player = OpenPlayPlayer::whereKey($player->id)->lockForUpdate()->firstOrFail();

            if ($actor->id !== $player->user_id && $actor->id !== $room->host_user_id) {
                throw new OpenPlayAuthorizationException('Only the player themself or the host can check them in.');
            }

            if (! in_array($room->status, ['waiting', 'in_progress'], true)) {
                throw new OpenPlayValidationException('This room is no longer taking check-ins.');
            }

            if ($player->checked_in_at !== null) {
                throw new OpenPlayValidationException('Already checked in.');
            }

            $player->update(['checked_in_at' => now()]);
            $room->update(['last_activity_at' => now()]);

            if ($room->status === 'in_progress') {
                // A late arrival can immediately backfill a free court
                // instead of waiting for the next match to finish.
                $this->matchmaking->generateRound($room->fresh());
            }

            return $player->fresh();
        });
    }

    public function startSession(OpenPlayRoom $room, User $host): OpenPlayRoom
    {
        return DB::transaction(function () use ($room, $host) {
            /** @var OpenPlayRoom $room */
            $room = OpenPlayRoom::whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($room->host_user_id !== $host->id) {
                throw new OpenPlayAuthorizationException('Only the host can start this session.');
            }

            if ($room->status !== 'waiting') {
                throw new OpenPlayValidationException('This session has already started.');
            }

            if ($room->players()->whereNotNull('checked_in_at')->count() < 4) {
                throw new OpenPlayValidationException('At least 4 checked-in players are needed to start a session.');
            }

            $room->update([
                'status' => 'in_progress',
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);

            $this->matchmaking->generateRound($room->fresh());

            return $room->fresh(['matches', 'players']);
        });
    }

    /**
     * @return array{match: OpenPlayMatch, new_matches: Collection<int, OpenPlayMatch>, room_state: OpenPlayRoom}
     */
    public function completeMatch(OpenPlayMatch $match, int $winnerTeam, User $actor): array
    {
        return DB::transaction(function () use ($match, $winnerTeam, $actor) {
            /** @var OpenPlayMatch $match */
            $match = OpenPlayMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $room = $match->room;

            if ($room->host_user_id !== $actor->id) {
                throw new OpenPlayAuthorizationException('Only the host can record match results.');
            }

            if ($match->status !== 'in_progress') {
                throw new OpenPlayValidationException('This match is not in progress.');
            }

            $match->update([
                'status' => 'completed',
                'winner_team' => $winnerTeam,
                'ended_at' => now(),
            ]);

            $room->update(['last_activity_at' => now()]);

            $matchPlayers = $match->matchPlayers()->with('player')->get();

            foreach ($matchPlayers as $matchPlayer) {
                $won = $matchPlayer->team === $winnerTeam;
                $player = $matchPlayer->player;

                $player->update([
                    'games_played' => $player->games_played + 1,
                    'wins' => $player->wins + ($won ? 1 : 0),
                    'losses' => $player->losses + ($won ? 0 : 1),
                    'current_status' => 'waiting',
                    'available_since' => now(),
                ]);

                $this->recordPlayerStat($matchPlayer->user_id, $won);
            }

            $newMatches = $this->matchmaking->generateRound($room->fresh());

            return [
                'match' => $match->fresh(),
                'new_matches' => $newMatches,
                'room_state' => $room->fresh(['matches', 'players']),
            ];
        });
    }

    public function endSession(OpenPlayRoom $room, User $host): OpenPlayRoom
    {
        return DB::transaction(function () use ($room, $host) {
            /** @var OpenPlayRoom $room */
            $room = OpenPlayRoom::whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($room->host_user_id !== $host->id) {
                throw new OpenPlayAuthorizationException('Only the host can end this session.');
            }

            if ($room->status !== 'in_progress') {
                throw new OpenPlayValidationException('This session is not in progress.');
            }

            $room->matches()->where('status', 'in_progress')->update(['status' => 'cancelled']);

            $room->update([
                'status' => 'finished',
                'ended_at' => now(),
            ]);

            return $room->fresh();
        });
    }

    /**
     * Force-ends any session the host forgot to close: in_progress rooms
     * with no activity (start, check-in, or match completion - see the
     * last_activity_at touches throughout this class) for at least $hours.
     * No host actor is involved - this is a system/scheduled action, so it
     * skips the authorization + "must be in_progress" guards endSession()
     * enforces for the interactive host flow.
     */
    public function autoEndStaleSessions(int $hours = 12): int
    {
        $cutoff = now()->subHours($hours);
        $count = 0;

        OpenPlayRoom::where('status', 'in_progress')
            ->where('last_activity_at', '<=', $cutoff)
            ->pluck('id')
            ->each(function (int $roomId) use ($cutoff, &$count) {
                DB::transaction(function () use ($roomId, $cutoff, &$count) {
                    /** @var OpenPlayRoom|null $room */
                    $room = OpenPlayRoom::whereKey($roomId)->lockForUpdate()->first();

                    // Re-check under the lock - activity may have landed
                    // between the query above and acquiring it.
                    if (! $room || $room->status !== 'in_progress' || $room->last_activity_at === null || $room->last_activity_at->gt($cutoff)) {
                        return;
                    }

                    $room->matches()->where('status', 'in_progress')->update(['status' => 'cancelled']);

                    $room->update([
                        'status' => 'finished',
                        'ended_at' => now(),
                    ]);

                    $count++;
                });
            });

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionSummary(OpenPlayRoom $room): array
    {
        $players = $room->players()->with('user')->get();

        $matches = $room->matches()
            ->where('status', 'completed')
            ->with(['court', 'matchPlayers.user'])
            ->orderBy('round_number')
            ->get();

        return [
            'room' => $room,
            'total_players' => $players->count(),
            'total_matches' => $matches->count(),
            'duration_minutes' => ($room->started_at && $room->ended_at)
                ? $room->started_at->diffInMinutes($room->ended_at)
                : null,
            'players' => $players->map(fn (OpenPlayPlayer $p) => [
                'user_id' => $p->user_id,
                'name' => $p->user->name,
                'games_played' => $p->games_played,
                'wins' => $p->wins,
                'losses' => $p->losses,
            ])->values(),
            // Round-by-round breakdown, so clicking into a session shows
            // real match info, not just the aggregate totals above.
            'matches' => $matches->map(fn (OpenPlayMatch $m) => [
                'round_number' => $m->round_number,
                'court_name' => $m->court->name,
                'winner_team' => $m->winner_team,
                'team_a' => $m->matchPlayers->where('team', 1)->map(fn ($mp) => ['user_id' => $mp->user_id, 'name' => $mp->user->name])->values(),
                'team_b' => $m->matchPlayers->where('team', 2)->map(fn ($mp) => ['user_id' => $mp->user_id, 'name' => $mp->user->name])->values(),
            ])->values(),
        ];
    }

    /**
     * Shared JSON shape for the live dashboard, consumed by both the web
     * poll endpoint and the Flutter API so they stay in sync.
     *
     * @return array<string, mixed>
     */
    public function dashboardPayload(OpenPlayRoom $room): array
    {
        $room->load([
            'roomCourts.court',
            'matches' => fn ($q) => $q->where('status', 'in_progress')->with('matchPlayers.user'),
            'players' => fn ($q) => $q->where('current_status', 'waiting')->orderBy('available_since')->with('user'),
        ]);

        $stats = OpenPlayPlayerStat::whereIn('user_id', $room->players->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        $rankFor = fn (int $userId) => $stats->get($userId)?->rank ?? 'Novice';

        $courts = $room->roomCourts->map(function (\App\Models\OpenPlayRoomCourt $roomCourt) use ($room, $rankFor) {
            $match = $room->matches->firstWhere('court_id', $roomCourt->court_id);

            return [
                'court_id' => $roomCourt->court_id,
                'court_name' => $roomCourt->court->name,
                'current_match' => $match ? [
                    'id' => $match->id,
                    'round_number' => $match->round_number,
                    'status' => $match->status,
                    'started_at' => $match->started_at,
                    'team_a' => $match->matchPlayers->where('team', 1)->map(fn ($mp) => [
                        'user_id' => $mp->user_id,
                        'name' => $mp->user->name,
                        'rank' => $rankFor($mp->user_id),
                    ])->values(),
                    'team_b' => $match->matchPlayers->where('team', 2)->map(fn ($mp) => [
                        'user_id' => $mp->user_id,
                        'name' => $mp->user->name,
                        'rank' => $rankFor($mp->user_id),
                    ])->values(),
                ] : null,
            ];
        })->values();

        $waiting = $room->players->map(fn (OpenPlayPlayer $p) => [
            'user_id' => $p->user_id,
            'name' => $p->user->name,
            'games_played' => $p->games_played,
            'rank' => $rankFor($p->user_id),
            'waiting_since' => $p->available_since,
            'checked_in' => $p->isCheckedIn(),
        ])->values();

        return [
            'room' => [
                'id' => $room->id,
                'title' => $room->title,
                'status' => $room->status,
                'current_round_number' => $room->current_round_number,
            ],
            'courts' => $courts,
            'waiting' => $waiting,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function recordPlayerStat(int $userId, bool $won): void
    {
        OpenPlayPlayerStat::firstOrCreate(['user_id' => $userId]);

        /** @var OpenPlayPlayerStat $stat */
        $stat = OpenPlayPlayerStat::where('user_id', $userId)->lockForUpdate()->first();

        $totalGames = $stat->total_games + 1;
        $totalWins = $stat->total_wins + ($won ? 1 : 0);
        $totalLosses = $stat->total_losses + ($won ? 0 : 1);
        $winRate = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 2) : 0.0;

        $stat->update([
            'total_games' => $totalGames,
            'total_wins' => $totalWins,
            'total_losses' => $totalLosses,
            'win_rate' => $winRate,
            'rank' => OpenPlayPlayerStat::rankFor($totalGames, $winRate),
            'last_played_at' => now(),
        ]);
    }
}
