<?php

namespace App\Http\Controllers;

use App\Exceptions\OpenPlayAuthorizationException;
use App\Exceptions\OpenPlayValidationException;
use App\Models\OpenPlayMatch;
use App\Models\OpenPlayMatchPlayer;
use App\Models\OpenPlayPlayer;
use App\Models\OpenPlayPlayerStat;
use App\Models\OpenPlayRoom;
use App\Models\User;
use App\Services\OpenPlayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OpenPlayController extends Controller
{
    public function __construct(protected OpenPlayService $openPlay) {}

    public function index()
    {
        $userId = Auth::id();

        $rooms = OpenPlayRoom::where('visibility', 'public')
            ->whereIn('status', ['waiting', 'in_progress'])
            ->withCount('players')
            ->withCount(['players as checked_in_count' => fn ($q) => $q->whereNotNull('checked_in_at')])
            // Only when logged in, so the "Joined" badge can be shown per room
            // without loading every room's full player list.
            ->when($userId, fn ($q) => $q->withExists(['players as joined' => fn ($q) => $q->where('user_id', $userId)]))
            ->with('host')
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->paginate(10);

        return view('open-play.index', ['rooms' => $rooms]);
    }

    public function show(OpenPlayRoom $room)
    {
        $room->load(['host', 'players.user', 'roomCourts.court']);

        return view('open-play.show', ['room' => $room]);
    }

    public function create()
    {
        /** @var User $user */
        $user = Auth::user();

        $bookings = $user->bookings()
            ->where('status', 'confirmed')
            ->whereDoesntHave('openPlayRoomCourt')
            ->with(['court', 'slots'])
            ->orderByDesc('id')
            ->get();

        return view('open-play.create', ['bookings' => $bookings]);
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
            $room = $this->openPlay->createRoom(Auth::user(), $data, $data['booking_ids']);
        } catch (OpenPlayAuthorizationException|OpenPlayValidationException $e) {
            return back()->withErrors(['booking_ids' => $e->getMessage()])->withInput();
        }

        return redirect()->route('open-play.show', $room)->with('status', 'Open Play room created.');
    }

    public function join(Request $request, OpenPlayRoom $room)
    {
        $data = $request->validate(['join_code' => ['nullable', 'string']]);

        try {
            $this->openPlay->joinRoom($room, Auth::user(), $data['join_code'] ?? null);
        } catch (OpenPlayValidationException $e) {
            return back()->withErrors(['join' => $e->getMessage()]);
        }

        return redirect()->route('open-play.show', $room)->with('status', 'You joined the room.');
    }

    public function leave(OpenPlayRoom $room)
    {
        try {
            $this->openPlay->leaveRoom($room, Auth::user());
        } catch (OpenPlayValidationException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        return redirect()->route('open-play.index')->with('status', 'You left the room.');
    }

    public function checkIn(OpenPlayRoom $room)
    {
        /** @var User $user */
        $user = Auth::user();
        $player = $room->players()->where('user_id', $user->id)->firstOrFail();

        try {
            $this->openPlay->checkIn($room, $player, $user);
        } catch (OpenPlayAuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (OpenPlayValidationException $e) {
            return back()->withErrors(['check_in' => $e->getMessage()]);
        }

        return back()->with('status', "You're checked in.");
    }

    public function checkInPlayer(OpenPlayRoom $room, OpenPlayPlayer $player)
    {
        try {
            $this->openPlay->checkIn($room, $player, Auth::user());
        } catch (OpenPlayAuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (OpenPlayValidationException $e) {
            return back()->withErrors(['check_in' => $e->getMessage()]);
        }

        return back()->with('status', $player->user->name.' is checked in.');
    }

    public function start(OpenPlayRoom $room)
    {
        try {
            $this->openPlay->startSession($room, Auth::user());
        } catch (OpenPlayAuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (OpenPlayValidationException $e) {
            return back()->withErrors(['start' => $e->getMessage()]);
        }

        return redirect()->route('open-play.dashboard', $room);
    }

    public function completeMatch(Request $request, OpenPlayRoom $room, OpenPlayMatch $match)
    {
        $data = $request->validate(['winner_team' => ['required', 'in:1,2']]);

        try {
            $this->openPlay->completeMatch($match, (int) $data['winner_team'], Auth::user());
        } catch (OpenPlayAuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (OpenPlayValidationException $e) {
            return back()->withErrors(['match' => $e->getMessage()]);
        }

        return redirect()->route('open-play.dashboard', $room);
    }

    public function end(OpenPlayRoom $room)
    {
        try {
            $this->openPlay->endSession($room, Auth::user());
        } catch (OpenPlayAuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (OpenPlayValidationException $e) {
            return back()->withErrors(['end' => $e->getMessage()]);
        }

        return redirect()->route('open-play.summary', $room);
    }

    public function dashboard(OpenPlayRoom $room)
    {
        return view('open-play.dashboard', ['room' => $room]);
    }

    public function poll(OpenPlayRoom $room)
    {
        return response()
            ->json($this->openPlay->dashboardPayload($room))
            ->header('Cache-Control', 'no-store');
    }

    public function summary(OpenPlayRoom $room)
    {
        return view('open-play.summary', ['summary' => $this->openPlay->sessionSummary($room)]);
    }

    public function history()
    {
        /** @var User $user */
        $user = Auth::user();

        $stat = OpenPlayPlayerStat::firstOrCreate(['user_id' => $user->id]);

        // "History" here means the open play sessions this user took part
        // in (as host or joined player), not a flat list of individual
        // matches - one row per FINISHED room they played, with their
        // personal record for that session. Rooms still waiting/in_progress
        // aren't history yet - they show up on /open-play until the host
        // ends them.
        $rooms = OpenPlayRoom::whereHas('players', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'finished')
            ->with('host')
            ->orderByDesc('session_date')
            ->orderByDesc('start_time')
            ->paginate(10);

        $records = OpenPlayMatchPlayer::query()
            ->join('open_play_matches', 'open_play_matches.id', '=', 'open_play_match_players.open_play_match_id')
            ->where('open_play_match_players.user_id', $user->id)
            ->where('open_play_matches.status', 'completed')
            ->whereIn('open_play_matches.open_play_room_id', $rooms->pluck('id'))
            ->selectRaw('open_play_matches.open_play_room_id as room_id, count(*) as games, sum(case when open_play_matches.winner_team = open_play_match_players.team then 1 else 0 end) as wins')
            ->groupBy('open_play_matches.open_play_room_id')
            ->get()
            ->keyBy('room_id');

        return view('open-play.history', ['stat' => $stat, 'rooms' => $rooms, 'records' => $records]);
    }

    public function player(User $user)
    {
        $stat = OpenPlayPlayerStat::firstOrCreate(['user_id' => $user->id]);

        $matches = OpenPlayMatchPlayer::where('user_id', $user->id)
            ->whereHas('match', fn ($q) => $q->where('status', 'completed'))
            ->with(['match.room', 'match.matchPlayers.user'])
            ->latest()
            ->limit(10)
            ->get();

        return view('open-play.player', ['profileUser' => $user, 'stat' => $stat, 'matches' => $matches]);
    }
}
