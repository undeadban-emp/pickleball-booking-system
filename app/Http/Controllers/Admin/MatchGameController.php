<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidMatchActionException;
use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\MatchGame;
use App\Services\MatchScoringService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchGameController extends Controller
{
    public function __construct(protected MatchScoringService $matches) {}

    public function point(GameMatch $match, MatchGame $game): JsonResponse
    {
        $this->assertBelongsToMatch($match, $game);

        return $this->respond(fn () => $this->matches->recordPoint($match, $game));
    }

    public function sideOut(GameMatch $match, MatchGame $game): JsonResponse
    {
        $this->assertBelongsToMatch($match, $game);

        return $this->respond(fn () => $this->matches->recordSideOut($match, $game));
    }

    public function timeout(Request $request, GameMatch $match, MatchGame $game): JsonResponse
    {
        $this->assertBelongsToMatch($match, $game);

        $data = $request->validate(['team' => ['required', 'integer', 'in:1,2']]);

        return $this->respond(fn () => $this->matches->recordTimeout($match, $game, $data['team']));
    }

    public function complete(GameMatch $match, MatchGame $game): JsonResponse
    {
        $this->assertBelongsToMatch($match, $game);

        return $this->respond(fn () => $this->matches->completeGame($match, $game));
    }

    public function rewind(Request $request, GameMatch $match, MatchGame $game): JsonResponse
    {
        $this->assertBelongsToMatch($match, $game);

        $data = $request->validate(['sequence' => ['required', 'integer', 'min:0']]);

        return $this->respond(function () use ($match, $game, $data) {
            $game = $this->matches->rewindGame($match, $game, $data['sequence']);

            return [
                'match' => $match->fresh(['players']),
                'game' => $game,
                'events' => $game->rallyEvents()->get(),
                'game_completed' => false,
            ];
        });
    }

    protected function assertBelongsToMatch(GameMatch $match, MatchGame $game): void
    {
        abort_unless($game->match_id === $match->id, 404);
    }

    protected function respond(Closure $action): JsonResponse
    {
        try {
            $result = $action();
        } catch (InvalidMatchActionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
