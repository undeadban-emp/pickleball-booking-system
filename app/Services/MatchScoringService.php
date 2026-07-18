<?php

namespace App\Services;

use App\Exceptions\InvalidMatchActionException;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\MatchGame;
use App\Models\MatchRallyEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owns pickleball scoring rules end to end. The scoreboard (match-scoreboard.js)
 * is a thin AJAX + rendering layer only — every rule lives here so there's a
 * single source of truth instead of duplicated client/server logic that can drift.
 *
 * Doubles court-position rotation (service-point scoring only — see
 * usesPositionRotation()): a team's two players occupy the right/left court.
 * Only Point rotates anyone — every point the serving team wins UNCONDITIONALLY
 * toggles the server to the opposite side, and their partner takes the other
 * side. This is a pure toggle, not a recompute-from-score-parity: after a
 * "Server 1 loses -> Server 2 takes over" handoff (which deliberately never
 * repositions anyone), the server can already be standing on the side score
 * parity would dictate — recomputing from parity there would silently no-op
 * (a real point with zero visible swap). Toggling always swaps, full stop.
 * Side Out never repositions anyone: serve just passes to whichever player
 * already happens to be standing in the correct court for the new server.
 * See seedTeamArrangement() (game start) and rotateServerPosition() (every
 * Point) for where positions actually change.
 *
 * server_number is a per-turn "Server 1 / Server 2 this turn" label, NOT a
 * stable player reference — it always resets to 1 the instant a side-out
 * hands serve to a team, regardless of which permanent slot that player
 * has. Identity is always derived on demand from (team, server_position).
 */
class MatchScoringService
{
    /**
     * @param  array{match_type:string,best_of:int,games_to:int,win_rule:string,timeouts_per_game:int,scoring_type:string,serving_team_first:int,event_name?:?string,referee_name?:?string,location?:?string,email_results_to?:?string,players:array<int,array{slot:int,team:int,name:string,gender?:string}>}  $data
     */
    public function createMatch(Booking $booking, array $data, ?User $creator): GameMatch
    {
        if ($booking->status !== 'completed' || $booking->checked_in_at === null) {
            throw new InvalidMatchActionException('A match can only be added to a checked-in booking.');
        }

        return DB::transaction(function () use ($booking, $data, $creator) {
            $match = $booking->matches()->create([
                'match_type' => $data['match_type'],
                'best_of' => $data['best_of'],
                'games_to' => $data['games_to'],
                'win_rule' => $data['win_rule'],
                'timeouts_per_game' => $data['timeouts_per_game'],
                'scoring_type' => $data['scoring_type'],
                'serving_team_first' => $data['serving_team_first'],
                'event_name' => $data['event_name'] ?? null,
                'referee_name' => $data['referee_name'] ?? null,
                'location' => $data['location'] ?? null,
                'email_results_to' => $data['email_results_to'] ?? null,
                'status' => 'setup',
                'created_by' => $creator?->id,
            ]);

            foreach ($data['players'] as $player) {
                $match->players()->create([
                    'team' => $player['team'],
                    'slot' => $player['slot'],
                    'position' => $player['slot'], // placeholder — startGame() establishes the real arrangement
                    'name' => $player['name'],
                    'gender' => $player['gender'] ?? 'unknown',
                ]);
            }

            return $match->fresh('players');
        });
    }

    /**
     * Locks in the settings (no further edits once this runs) and opens game 1.
     */
    public function startMatch(GameMatch $match): GameMatch
    {
        if ($match->status !== 'setup') {
            throw new InvalidMatchActionException('Settings cannot be changed once a match has started.');
        }

        return DB::transaction(function () use ($match) {
            $match->update(['status' => 'in_progress', 'started_at' => now()]);
            $this->startGame($match, 1, (int) $match->serving_team_first);

            return $match->fresh(['players', 'games']);
        });
    }

    public function startGame(GameMatch $match, int $gameNumber, int $servingTeam): MatchGame
    {
        // Doubles: the team opening a new game only gets one server before the
        // first side-out, so the opening server is already treated as the
        // team's "second" server (the classic "0-0-2" start). Singles has no
        // such rule — a side-out always just swaps ends immediately.
        $serverNumber = $match->isSinglesMatch() ? 1 : 2;
        $receivingTeam = $servingTeam === 1 ? 2 : 1;

        $serverSide = 1;

        if ($this->usesPositionRotation($match)) {
            // Both teams start a fresh game at 0 (even) — right court. This is the
            // one moment we pick a player by SLOT rather than by current court
            // side, since there's no "current server" yet to derive identity from.
            $serverSide = $this->seedTeamArrangement($match, $servingTeam, $serverNumber, 0);
            $this->seedTeamArrangement($match, $receivingTeam, 1, 0);
        }

        $startingPositions = $match->players()->pluck('position', 'id')->toArray();

        return $match->games()->create([
            'game_number' => $gameNumber,
            'serving_team' => $servingTeam,
            'server_position' => $serverSide,
            'server_number' => $serverNumber,
            'starting_serving_team' => $servingTeam,
            'starting_server_position' => $serverSide,
            'starting_server_number' => $serverNumber,
            'starting_player_positions' => $startingPositions,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * The serving team won the rally: they score, and the same player keeps serving.
     * This is identical under service-point and rally-point scoring. Under
     * service-point doubles, the server's court side flips to match the new
     * score's parity (real pickleball rule) — their partner takes the other side.
     */
    public function recordPoint(GameMatch $match, MatchGame $game): array
    {
        return DB::transaction(function () use ($match, $game) {
            // Re-fetch with a row lock so two near-simultaneous requests for the
            // same game (e.g. a double-tap on the Point button) are serialized —
            // the second one waits for the first to commit, then reads its
            // already-updated score/server state instead of racing against it.
            $game = MatchGame::whereKey($game->id)->lockForUpdate()->firstOrFail();

            $this->assertGameLive($match, $game);

            $team = $game->serving_team;
            $column = $team === 1 ? 'team1_score' : 'team2_score';
            $newScore = $game->{$column} + 1;

            $updates = [$column => $newScore];

            if ($this->usesPositionRotation($match)) {
                // Identify the current server by their COURT SIDE, not server_number/slot —
                // server_number is a per-turn "1st or 2nd this turn" label that gets reset
                // on every side-out, so it no longer reliably maps to a specific player once
                // a side-out has handed serve to whichever slot happened to be on the right.
                $updates['server_position'] = $this->rotateServerPosition($match, $team, $game->server_position);
            }

            $game->update($updates);

            $this->logEvent($match, $game, 'point', $team);

            return $this->afterAction($match, $game);
        });
    }

    /**
     * The serving team lost the rally. What happens next depends on scoring type:
     * - Rally point: the receiving team scores AND immediately becomes the new server.
     * - Service point (traditional): no score change, serve rotates per the
     *   doubles/singles rules below. Nobody's court side changes on a side-out —
     *   only Point does that. The new server is simply whoever the rotation has
     *   already left in the correct spot.
     */
    public function recordSideOut(GameMatch $match, MatchGame $game): array
    {
        return DB::transaction(function () use ($match, $game) {
            $game = MatchGame::whereKey($game->id)->lockForUpdate()->firstOrFail();

            return $this->applySideOut($match, $game);
        });
    }

    protected function applySideOut(GameMatch $match, MatchGame $game): array
    {
        $this->assertGameLive($match, $game);

        if ($match->scoring_type === 'rally') {
            $receivingTeam = $game->serving_team === 1 ? 2 : 1;
            $column = $receivingTeam === 1 ? 'team1_score' : 'team2_score';

            $game->update([
                $column => $game->{$column} + 1,
                'serving_team' => $receivingTeam,
                'server_number' => 1,
            ]);
        } elseif ($match->isSinglesMatch()) {
            $newServingTeam = $game->serving_team === 1 ? 2 : 1;

            $game->update([
                'serving_team' => $newServingTeam,
                'server_number' => 1,
            ]);
        } elseif ((int) $game->server_number === 1) {
            // First server of this team lost the rally — their partner takes over,
            // serving from wherever they're already standing (no repositioning).
            // A team only occupies 2 court sides, so the partner is simply
            // whichever side isn't the current server's — no lookup needed.
            $game->update([
                'server_number' => 2,
                'server_position' => $game->server_position === 1 ? 2 : 1,
            ]);
        } else {
            // Second server lost the rally too — full side-out. The new team's
            // first server for this turn is whoever is currently standing in
            // their right court, and they are ALWAYS labeled "Server 1" for this
            // new turn — regardless of which permanent slot they were originally
            // entered as. Nothing here moves anyone; identity is looked up on
            // demand (via team + server_position) wherever it's next needed.
            $newServingTeam = $game->serving_team === 1 ? 2 : 1;

            $game->update([
                'serving_team' => $newServingTeam,
                'server_number' => 1,
                'server_position' => 1,
            ]);
        }

        $this->logEvent($match, $game, 'side_out', $game->serving_team);

        return $this->afterAction($match, $game);
    }

    public function recordTimeout(GameMatch $match, MatchGame $game, int $team): array
    {
        return DB::transaction(function () use ($match, $game, $team) {
            $game = MatchGame::whereKey($game->id)->lockForUpdate()->firstOrFail();

            $this->assertGameLive($match, $game);

            if ($game->timeoutsUsedFor($team) >= $match->timeouts_per_game) {
                throw new InvalidMatchActionException("Team {$team} has no timeouts remaining this game.");
            }

            $column = $team === 1 ? 'team1_timeouts_used' : 'team2_timeouts_used';
            $game->update([$column => $game->{$column} + 1]);

            $this->logEvent($match, $game, 'timeout', $team);

            return $this->afterAction($match, $game);
        });
    }

    /**
     * Explicit confirm action for the "Game Completed" modal. Advances to the
     * next game (loser serves first), or — if the match is decided — moves the
     * match to "verifying" so the Match Results screen can be reviewed before
     * the final, separate completeMatch() save.
     */
    public function completeGame(GameMatch $match, MatchGame $game): array
    {
        return DB::transaction(function () use ($match, $game) {
            $game = MatchGame::whereKey($game->id)->lockForUpdate()->firstOrFail();

            if ($game->status !== 'in_progress') {
                throw new InvalidMatchActionException('This game has already been completed.');
            }

            $winner = $this->checkGameWin($game, $match);

            if ($winner === null) {
                throw new InvalidMatchActionException('This game has not reached a win condition yet.');
            }

            $game->update([
                'status' => 'completed',
                'winner_team' => $winner,
                'completed_at' => now(),
            ]);

            $gamesWonByTeam1 = $match->games()->where('winner_team', 1)->count();
            $gamesWonByTeam2 = $match->games()->where('winner_team', 2)->count();
            $gamesToWinMatch = $match->gamesToWinMatch();

            $matchWinner = match (true) {
                $gamesWonByTeam1 >= $gamesToWinMatch => 1,
                $gamesWonByTeam2 >= $gamesToWinMatch => 2,
                default => null,
            };

            $matchDecided = $matchWinner !== null || ($gamesWonByTeam1 + $gamesWonByTeam2) >= $match->best_of;

            if ($matchDecided) {
                $matchWinner ??= $gamesWonByTeam1 > $gamesWonByTeam2 ? 1 : 2;

                $match->update([
                    'status' => 'verifying',
                    'winner_team' => $matchWinner,
                ]);
            } else {
                // Official rule: the team that lost the game just finished serves first next game.
                $this->startGame($match, $game->game_number + 1, $winner === 1 ? 2 : 1);
            }

            return [
                'match' => $match->fresh(['games', 'players']),
                'game' => $game->fresh(),
                'match_completed' => $matchDecided,
            ];
        });
    }

    /**
     * "Save & Return to Booking" — the final confirmation once the Match Results
     * screen has been reviewed. Separate from completeGame() so the referee gets
     * one last look before the match is locked in.
     */
    public function completeMatch(GameMatch $match): GameMatch
    {
        if ($match->status !== 'verifying') {
            throw new InvalidMatchActionException('This match is not ready to be saved yet.');
        }

        $match->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $match->fresh(['games', 'players']);
    }

    /**
     * Deliberate, explicit "undo to here" for the live game — not silent
     * deletion. Truncates rally events after $toSequence and reconstructs the
     * game's score/server/timeout/court-position state from what remains.
     * $toSequence = 0 rewinds all the way back to how the game started (the
     * starting_* snapshot captured in startGame()) — that's a special case of
     * this same operation, not a separate "restart" action.
     */
    public function rewindGame(GameMatch $match, MatchGame $game, int $toSequence): MatchGame
    {
        return DB::transaction(function () use ($match, $game, $toSequence) {
            $game = MatchGame::whereKey($game->id)->lockForUpdate()->firstOrFail();

            if ($game->status !== 'in_progress') {
                throw new InvalidMatchActionException('Only the current live game can be rewound.');
            }

            $game->rallyEvents()->where('sequence', '>', $toSequence)->delete();

            $anchor = $toSequence > 0
                ? $game->rallyEvents()->where('sequence', $toSequence)->first()
                : null;

            if ($anchor) {
                $game->update([
                    'team1_score' => $anchor->team1_score_after,
                    'team2_score' => $anchor->team2_score_after,
                    'serving_team' => $anchor->serving_team_after,
                    'server_position' => $anchor->server_position_after,
                    'server_number' => $anchor->server_number_after,
                ]);
                $positions = $anchor->player_positions_after ?? [];
            } else {
                $game->update([
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'serving_team' => $game->starting_serving_team,
                    'server_position' => $game->starting_server_position,
                    'server_number' => $game->starting_server_number,
                ]);
                $positions = $game->starting_player_positions ?? [];
            }

            foreach ($positions as $playerId => $position) {
                $match->players()->where('id', $playerId)->update(['position' => $position]);
            }

            $game->update([
                'team1_timeouts_used' => $game->rallyEvents()->where('event_type', 'timeout')->where('acting_team', 1)->count(),
                'team2_timeouts_used' => $game->rallyEvents()->where('event_type', 'timeout')->where('acting_team', 2)->count(),
            ]);

            return $game->fresh();
        });
    }

    public function checkGameWin(MatchGame $game, GameMatch $match): ?int
    {
        $target = $match->games_to;
        $leader = max($game->team1_score, $game->team2_score);
        $trailer = min($game->team1_score, $game->team2_score);

        if ($leader < $target) {
            return null;
        }

        // win_by_1 and first_to both end the game the instant the target is reached.
        if ($match->win_rule === 'win_by_2' && ($leader - $trailer) < 2) {
            return null;
        }

        return $game->team1_score > $game->team2_score ? 1 : 2;
    }

    /**
     * Court-side rotation only applies to doubles under traditional
     * service-point scoring — singles has only one player per side (nothing
     * to swap), and rally-point's every-rally-scores model doesn't use the
     * classic right/left serving convention.
     */
    protected function usesPositionRotation(GameMatch $match): bool
    {
        return ! $match->isSinglesMatch() && $match->scoring_type === 'service';
    }

    /**
     * Game-start only: assigns $team's designated server (by stable SLOT — the
     * only identity available before any player has an established court
     * side) to the side matching $score's parity, and their partner to the
     * opposite side. Never called mid-game — see rotateServerPosition().
     */
    protected function seedTeamArrangement(GameMatch $match, int $team, int $servingSlot, int $score): int
    {
        $serverSide = $score % 2 === 0 ? 1 : 2; // 1 = right court, 2 = left court
        $partnerSlot = $servingSlot === 1 ? 2 : 1;
        $partnerSide = $serverSide === 1 ? 2 : 1;

        $match->players()->where('team', $team)->where('slot', $servingSlot)->update(['position' => $serverSide]);
        $match->players()->where('team', $team)->where('slot', $partnerSlot)->update(['position' => $partnerSide]);

        return $serverSide;
    }

    /**
     * Called on every Point win: swaps the CURRENT server (identified by their
     * existing court side — not slot/server_number, which is a per-turn label
     * that gets reset on every side-out and stops mapping to a specific player)
     * with their partner. This is a pure, UNCONDITIONAL toggle — not a
     * recompute-from-score-parity — because the "Server 1 loses -> Server 2
     * takes over" side-out handoff deliberately leaves positions untouched
     * (per the rule), which can leave the server standing on the side that
     * score parity would already dictate. Recomputing from parity in that
     * situation would set a player's position to the value it already has —
     * a real point scored with zero visible swap. Toggling always produces a
     * visible swap, which is what "every point swaps, no exceptions" means.
     * This is the ONLY place positions change mid-game — Side Out deliberately
     * never repositions anyone; see recordSideOut().
     */
    protected function rotateServerPosition(GameMatch $match, int $team, int $currentServerPosition): int
    {
        $serverSide = $currentServerPosition === 1 ? 2 : 1;

        // Called from within recordPoint()'s transaction — lock these two rows
        // too, not just the game row, so a concurrent request can't read a
        // half-swapped arrangement between the server's update and the partner's.
        $teamPlayers = $match->players()->where('team', $team)->lockForUpdate()->get();
        $server = $teamPlayers->firstWhere('position', $currentServerPosition);
        $partner = $teamPlayers->first(fn ($p) => $server === null || $p->id !== $server->id);

        $server?->update(['position' => $serverSide]);
        $partner?->update(['position' => $serverSide === 1 ? 2 : 1]);

        return $serverSide;
    }

    protected function assertGameLive(GameMatch $match, MatchGame $game): void
    {
        if ($match->status !== 'in_progress') {
            throw new InvalidMatchActionException('This match is not currently in progress.');
        }

        if ($game->status !== 'in_progress') {
            throw new InvalidMatchActionException('This game has already been completed.');
        }
    }

    protected function logEvent(GameMatch $match, MatchGame $game, string $eventType, ?int $actingTeam, array $meta = []): MatchRallyEvent
    {
        $nextSequence = ($game->rallyEvents()->max('sequence') ?? 0) + 1;

        return $game->rallyEvents()->create([
            'sequence' => $nextSequence,
            'event_type' => $eventType,
            'acting_team' => $actingTeam,
            'team1_score_after' => $game->team1_score,
            'team2_score_after' => $game->team2_score,
            'serving_team_after' => $game->serving_team,
            'server_position_after' => $game->server_position,
            'server_number_after' => $game->server_number,
            'player_positions_after' => $match->players()->pluck('position', 'id')->toArray(),
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * Common "what changed" payload returned after point/side-out/timeout.
     * Win detection here is read-only (checkGameWin doesn't mutate anything) —
     * the game stays "in_progress" until completeGame() is explicitly confirmed,
     * so the scoreboard can show the "Game Completed" modal first.
     *
     * `players` is eager-loaded here (unlike a bare fresh()) because
     * applyTeamArrangement() may have just changed their court-side positions —
     * the scoreboard needs those in every response, not just at page load.
     */
    protected function afterAction(GameMatch $match, MatchGame $game): array
    {
        return [
            'match' => $match->fresh(['players']),
            'game' => $game->fresh(),
            'events' => $game->rallyEvents()->get(),
            'pending_winner_team' => $this->checkGameWin($game, $match),
            'game_completed' => $this->checkGameWin($game, $match) !== null,
        ];
    }
}
