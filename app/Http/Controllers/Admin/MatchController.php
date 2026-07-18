<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidMatchActionException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Services\MatchScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MatchController extends Controller
{
    public function __construct(protected MatchScoringService $matches) {}

    public function create(Booking $booking)
    {
        abort_unless($booking->status === 'completed' && $booking->checked_in_at !== null, 404);

        return view('matches.create', ['booking' => $booking]);
    }

    public function store(Request $request, Booking $booking)
    {
        $data = $this->validateSettings($request);

        try {
            $match = $this->matches->createMatch($booking, $data, Auth::user());
        } catch (InvalidMatchActionException $e) {
            return back()->withErrors(['match' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.matches.show', $match)
            ->with('status', 'Match created. Review the settings below, then start the match.');
    }

    public function show(GameMatch $match)
    {
        $match->load(['booking.court', 'players', 'games.rallyEvents']);

        return view('matches.show', ['match' => $match]);
    }

    public function start(GameMatch $match)
    {
        try {
            $this->matches->startMatch($match);
        } catch (InvalidMatchActionException $e) {
            return back()->withErrors(['match' => $e->getMessage()]);
        }

        return redirect()->route('admin.matches.show', $match);
    }

    public function complete(GameMatch $match)
    {
        try {
            $this->matches->completeMatch($match);
        } catch (InvalidMatchActionException $e) {
            return back()->withErrors(['match' => $e->getMessage()]);
        }

        return redirect()->route('admin.bookings.index')
            ->with('status', "Match results saved for booking {$match->booking->booking_code}.");
    }

    public function results(GameMatch $match)
    {
        $match->load(['booking.court', 'players', 'games']);

        return view('matches.partials.match-results', ['match' => $match, 'standalone' => true]);
    }

    protected function validateSettings(Request $request): array
    {
        $matchType = $request->string('match_type')->toString();

        $data = $request->validate([
            'match_type' => ['required', 'in:singles,doubles'],
            'best_of' => ['required', 'integer', 'in:1,3,5'],
            'games_to' => ['required', 'integer', 'in:11,15,21'],
            'win_rule' => ['required', 'in:first_to,win_by_1,win_by_2'],
            'timeouts_per_game' => ['required', 'integer', 'between:0,3'],
            'scoring_type' => ['required', 'in:rally,service'],
            'serving_team_first' => ['required', 'integer', 'in:1,2'],
            'event_name' => ['nullable', 'string', 'max:120'],
            'referee_name' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:150'],
            'email_results_to' => ['nullable', 'email', 'max:255'],
            'players' => ['required', 'array', 'size:'.($matchType === 'singles' ? 2 : 4)],
            'players.*.team' => ['required', 'integer', 'in:1,2'],
            'players.*.name' => ['required', 'string', 'max:80'],
            'players.*.gender' => ['nullable', 'in:unknown,f,m,x'],
        ]);

        // Slot = 1st or 2nd player submitted for that specific team — stable identity,
        // independent of which court side they're currently standing in. See
        // MatchScoringService for how this drives "first server" / "second server".
        $teamCounts = [1 => 0, 2 => 0];
        foreach ($data['players'] as &$player) {
            $team = $player['team'];
            $teamCounts[$team]++;
            $player['slot'] = $teamCounts[$team];
        }

        return $data;
    }
}
