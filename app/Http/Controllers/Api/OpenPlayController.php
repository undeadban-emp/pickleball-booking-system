<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OpenPlayAuthorizationException;
use App\Exceptions\OpenPlayValidationException;
use App\Http\Controllers\Controller;
use App\Models\OpenPlayMatch;
use App\Models\OpenPlayMatchPlayer;
use App\Models\OpenPlayPlayerStat;
use App\Models\OpenPlayRoom;
use App\Models\User;
use App\Services\OpenPlayService;
use Illuminate\Http\Request;

class OpenPlayController extends Controller
{
    public function __construct(protected OpenPlayService $openPlay) {}

    public function index()
    {
        return OpenPlayRoom::where('visibility', 'public')
            ->whereIn('status', ['waiting', 'in_progress'])
            ->withCount('players')
            ->withCount(['players as checked_in_count' => fn ($q) => $q->whereNotNull('checked_in_at')])
            ->with('host')
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->paginate(15);
    }

    public function show(OpenPlayRoom $room)
    {
        return $room->load(['host', 'players.user', 'roomCourts.court']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'booking_ids' => ['required', 'array', 'min:1'],
            'booking_ids.*' => ['integer', 'exists:bookings,id'],
            'skill_level' => ['nullable', 'in:beginner,intermediate,advanced,any'],
            'max_players' => ['required', 'integer', 'min:4', 'max:64'],
            'match_format' => ['nullable', 'in:first_to,timed'],
            'points_target' => ['nullable', 'integer', 'min:1', 'max:21'],
            'timer_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'visibility' => ['nullable', 'in:public,private'],
            'join_code' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $room = $this->openPlay->createRoom($request->user(), $data, $data['booking_ids']);
        } catch (OpenPlayAuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (OpenPlayValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $room], 201);
    }

    public function join(Request $request, OpenPlayRoom $room)
    {
        $data = $request->validate(['join_code' => ['nullable', 'string']]);

        try {
            $player = $this->openPlay->joinRoom($room, $request->user(), $data['join_code'] ?? null);
        } catch (OpenPlayValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $player], 201);
    }

    public function leave(Request $request, OpenPlayRoom $room)
    {
        try {
            $this->openPlay->leaveRoom($room, $request->user());
        } catch (OpenPlayValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Left the room.']);
    }

    public function checkIn(Request $request, OpenPlayRoom $room)
    {
        $user = $request->user();
        $player = $room->players()->where('user_id', $user->id)->firstOrFail();

        try {
            $player = $this->openPlay->checkIn($room, $player, $user);
        } catch (OpenPlayAuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (OpenPlayValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $player]);
    }

    public function start(Request $request, OpenPlayRoom $room)
    {
        try {
            $room = $this->openPlay->startSession($room, $request->user());
        } catch (OpenPlayAuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (OpenPlayValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $room]);
    }

    public function completeMatch(Request $request, OpenPlayRoom $room, OpenPlayMatch $match)
    {
        $data = $request->validate(['winner_team' => ['required', 'in:1,2']]);

        try {
            $result = $this->openPlay->completeMatch($match, (int) $data['winner_team'], $request->user());
        } catch (OpenPlayAuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (OpenPlayValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function end(Request $request, OpenPlayRoom $room)
    {
        try {
            $room = $this->openPlay->endSession($room, $request->user());
        } catch (OpenPlayAuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (OpenPlayValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $room]);
    }

    public function dashboard(OpenPlayRoom $room)
    {
        return response()
            ->json($this->openPlay->dashboardPayload($room))
            ->header('Cache-Control', 'no-store');
    }

    public function summary(OpenPlayRoom $room)
    {
        return response()->json(['data' => $this->openPlay->sessionSummary($room)]);
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $stat = OpenPlayPlayerStat::firstOrCreate(['user_id' => $user->id]);

        $matches = OpenPlayMatchPlayer::where('user_id', $user->id)
            ->with(['match.room', 'match.matchPlayers.user'])
            ->latest()
            ->paginate(15);

        return response()->json(['data' => ['stat' => $stat, 'matches' => $matches]]);
    }

    public function player(User $user)
    {
        $stat = OpenPlayPlayerStat::firstOrCreate(['user_id' => $user->id]);

        $matches = OpenPlayMatchPlayer::where('user_id', $user->id)
            ->with(['match.room'])
            ->latest()
            ->limit(10)
            ->get();

        return response()->json(['data' => ['user' => $user, 'stat' => $stat, 'matches' => $matches]]);
    }
}
