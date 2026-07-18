@php
    $currentGame = $match->games->sortByDesc('game_number')->first();
@endphp

<x-layouts.admin :title="'Match — '.$match->booking->booking_code">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left"></i>
            Bookings
        </a>
    </div>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
                {{ $match->event_name ?: 'Match' }}
            </h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                {{ $match->booking->booking_code }} · {{ $match->booking->court->name }}
            </p>
        </div>
    </div>

    @error('match')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            {{ $message }}
        </div>
    @enderror

    @if ($match->status === 'setup')
        {{-- Settings review, locked once started --}}
        <div class="mt-6 rounded-2xl border border-ink-200 bg-white p-6 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Ready to start</p>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Settings cannot be changed once the match starts.</p>

            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Type</dt>
                    <dd class="mt-0.5 font-medium text-ink-900 capitalize dark:text-ink-100">{{ $match->match_type }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Best of / to</dt>
                    <dd class="mt-0.5 font-medium text-ink-900 dark:text-ink-100">{{ $match->best_of }} games, to {{ $match->games_to }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Win rule</dt>
                    <dd class="mt-0.5 font-medium text-ink-900 dark:text-ink-100">{{ str($match->win_rule)->replace('_', ' ')->headline() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Scoring</dt>
                    <dd class="mt-0.5 font-medium text-ink-900 capitalize dark:text-ink-100">{{ $match->scoring_type }} point</dd>
                </div>
            </dl>

            <div class="mt-5 grid grid-cols-1 gap-4 border-t border-ink-100 pt-4 sm:grid-cols-2 dark:border-ink-800">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Team 1</p>
                    <p class="mt-1 font-medium text-ink-900 dark:text-ink-100">{{ $match->teamName(1) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Team 2</p>
                    <p class="mt-1 font-medium text-ink-900 dark:text-ink-100">{{ $match->teamName(2) }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.matches.start', $match) }}" class="mt-6">
                @csrf
                <button type="submit" class="w-fit rounded-full bg-ink-950 px-6 py-3 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                    Start match
                </button>
            </form>
        </div>
    @elseif (in_array($match->status, ['verifying', 'completed']))
        <div class="mt-6">
            @include('matches.partials.match-results', ['match' => $match])
        </div>
    @else
        {{-- Live scoreboard --}}
        <div
            class="mt-6"
            x-data="matchScoreboard({
                match: @js([
                    'id' => $match->id,
                    'status' => $match->status,
                    'match_type' => $match->match_type,
                    'best_of' => $match->best_of,
                    'games_to' => $match->games_to,
                    'win_rule' => $match->win_rule,
                    'timeouts_per_game' => $match->timeouts_per_game,
                    'scoring_type' => $match->scoring_type,
                    'winner_team' => $match->winner_team,
                    'players' => $match->players->map(fn ($p) => ['id' => $p->id, 'team' => $p->team, 'slot' => $p->slot, 'position' => $p->position, 'name' => $p->name, 'gender' => $p->gender])->values(),
                    'games' => $match->games->map(fn ($g) => ['id' => $g->id, 'game_number' => $g->game_number])->values(),
                ]),
                game: @js([
                    'id' => $currentGame->id,
                    'game_number' => $currentGame->game_number,
                    'team1_score' => $currentGame->team1_score,
                    'team2_score' => $currentGame->team2_score,
                    'serving_team' => $currentGame->serving_team,
                    'server_position' => $currentGame->server_position,
                    'server_number' => $currentGame->server_number,
                    'starting_serving_team' => $currentGame->starting_serving_team,
                    'starting_server_position' => $currentGame->starting_server_position,
                    'starting_server_number' => $currentGame->starting_server_number,
                    'starting_player_positions' => $currentGame->starting_player_positions,
                    'team1_timeouts_used' => $currentGame->team1_timeouts_used,
                    'team2_timeouts_used' => $currentGame->team2_timeouts_used,
                    'status' => $currentGame->status,
                ]),
                events: @js($currentGame->rallyEvents->values()),
                csrfToken: document.querySelector('meta[name=csrf-token]').content,
                urls: {
                    point: '{{ route('admin.matches.games.point', ['match' => $match, 'game' => '__GAME_ID__']) }}',
                    sideOut: '{{ route('admin.matches.games.side-out', ['match' => $match, 'game' => '__GAME_ID__']) }}',
                    timeout: '{{ route('admin.matches.games.timeout', ['match' => $match, 'game' => '__GAME_ID__']) }}',
                    completeGame: '{{ route('admin.matches.games.complete', ['match' => $match, 'game' => '__GAME_ID__']) }}',
                    rewind: '{{ route('admin.matches.games.rewind', ['match' => $match, 'game' => '__GAME_ID__']) }}',
                    show: '{{ route('admin.matches.show', $match) }}',
                },
            })"
        >
            {{-- Score bar — pulsing dot + serving team's name make "who's serving"
            readable at a glance, without needing to scan the court below. --}}
            <div class="relative overflow-hidden rounded-2xl bg-white px-5 py-4 text-center shadow-sm dark:bg-ink-900">
                <div class="absolute inset-y-0 w-1.5 bg-[#2DBF48]" :class="displayed.serving_team === 1 ? 'left-0' : 'right-0'"></div>

                <div class="flex items-center justify-center gap-1.5">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#2DBF48] opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-[#2DBF48]"></span>
                    </span>
                    <span class="text-xs font-bold tracking-wide text-[#1a8f35] uppercase dark:text-[#2DBF48]">
                        <span x-text="teamName(displayed.serving_team)"></span> (serving)
                    </span>
                </div>

                <p class="mt-1 font-display text-3xl font-bold text-ink-950 dark:text-white" x-text="scoreLabel"></p>
            </div>

            <p x-show="errorMessage" x-cloak x-text="errorMessage" class="mt-3 text-center text-sm font-medium text-rose-600 dark:text-rose-400"></p>

            {{-- Court panel: light-blue outer card --}}
            <div class="mt-1 rounded-2xl bg-sky-100 p-3 dark:bg-sky-950/40">
                {{-- Court grid: white outer border + white grid lines (via gap-1 on a
                white background) separating all four quadrants and the two net bars —
                matching the reference exactly, not the earlier thick-dark-border
                version. #133156 navy cells, #2DBF48 serving accent
                hugging just the player-name text. aspect-[9/4] locks in a wide
                rectangle regardless of name length; column tracks are percentage-based
                so each net bar is 15% of the width (30% combined). --}}
                {{-- ring-1 ring-black draws a second, black border just outside the white
                one — it's a box-shadow, so overflow-hidden (which only clips the grid's
                own content) doesn't cut it off. --}}
                <div class="mx-auto grid aspect-[9/4] w-[90%] grid-cols-[35%_15%_15%_35%] gap-1 overflow-hidden rounded-xl border-4 border-white bg-white ring-1 ring-black dark:border-ink-700">
                    <div class="grid grid-rows-2 gap-1 bg-white">
                        <template x-for="player in teamPlayers(1)" :key="player.id">
                            <div class="flex items-center justify-center bg-[#133156] px-3">
                                <span class="border-r-4 pr-1 text-lg font-semibold text-white" :class="isServing(player) ? 'border-[#2DBF48]' : 'border-[#133156]'">
                                    <span x-show="isServing(player)" class="text-[#2DBF48]">* </span><span x-text="player.name"></span>
                                </span>
                            </div>
                        </template>
                    </div>

                    <div class="bg-[#8B1E2B]"></div>
                    <div class="bg-[#8B1E2B]"></div>

                    <div class="grid grid-rows-2 gap-1 bg-white">
                        <template x-for="player in teamPlayers(2)" :key="player.id">
                            <div class="flex items-center justify-center bg-[#133156] px-3">
                                <span class="border-l-4 pl-1 text-lg font-semibold text-white" :class="isServing(player) ? 'border-[#2DBF48]' : 'border-[#133156]'">
                                    <span x-show="isServing(player)" class="text-[#2DBF48]">* </span><span x-text="player.name"></span>
                                </span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Timeout row --}}
                <div class="mt-2 flex items-center justify-between px-1">
                    <button type="button" @click="timeoutFor(1)" :disabled="!canTimeout(1)" class="relative inline-flex items-center rounded-full bg-blue-500 px-5 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-40">
                        TMO
                        <span class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white" x-text="timeoutsUsed(1)"></span>
                    </button>

                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-500 text-white">
                        <i class="ph ph-timer text-base"></i>
                    </span>

                    <button type="button" @click="timeoutFor(2)" :disabled="!canTimeout(2)" class="relative inline-flex items-center rounded-full bg-blue-500 px-5 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-40">
                        TMO
                        <span class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white" x-text="timeoutsUsed(2)"></span>
                    </button>
                </div>
            </div>

            {{-- Game status bar --}}
            <div class="rounded-full bg-ink-100 px-4 py-2.5 text-center dark:bg-ink-800">
                <p class="text-sm font-bold text-ink-700 dark:text-ink-200">Game <span x-text="game.game_number"></span> of {{ $match->best_of }}</p>
            </div>

            {{-- Action buttons --}}
            <div class="grid grid-cols-2 gap-3">
                <button type="button" @click="sideOut()" :disabled="!canScore || loading" class="rounded-xl bg-blue-500 px-6 py-4 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40">
                    Side Out
                </button>
                <button type="button" @click="point()" :disabled="!canScore || loading" class="rounded-xl bg-blue-500 px-6 py-4 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40">
                    Point
                </button>
            </div>

            {{-- Rally history --}}
            <div class="mt-5 flex items-center justify-between gap-3 rounded-full bg-white px-4 py-3 shadow-sm dark:bg-ink-900">
                <button type="button" @click="historyBack()" :disabled="events.length === 0 || historyIndex === -1" class="rounded-lg p-2 text-ink-500 hover:bg-ink-100 disabled:opacity-30 dark:text-ink-400 dark:hover:bg-ink-800">
                    <i class="ph ph-caret-left text-lg"></i>
                </button>

                <div class="text-center">
                    <p class="text-sm font-bold text-ink-800 dark:text-ink-100">Rally History</p>
                    <p class="text-[11px] text-ink-500 dark:text-ink-400">
                        <template x-if="isLive">
                            <span>Live</span>
                        </template>
                        <template x-if="!isLive && historyIndex === -1">
                            <span>Game start</span>
                        </template>
                        <template x-if="!isLive && historyIndex !== -1">
                            <span>Event <span x-text="historyIndex + 1"></span> of <span x-text="events.length"></span></span>
                        </template>
                    </p>
                </div>

                <button type="button" @click="historyForward()" :disabled="isLive" class="rounded-lg p-2 text-ink-500 hover:bg-ink-100 disabled:opacity-30 dark:text-ink-400 dark:hover:bg-ink-800">
                    <i class="ph ph-caret-right text-lg"></i>
                </button>
            </div>

            {{-- Bottom controls --}}
            <div class="mt-3 flex justify-center gap-3">
                <button type="button" @click="resumePlay()" :disabled="isLive" class="rounded-full bg-blue-500 px-6 py-2.5 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-40">
                    Resume Play
                </button>
                <button type="button" @click="undoToHere()" x-show="!isLive" x-cloak class="rounded-full border border-blue-500 px-6 py-2.5 text-xs font-bold text-blue-600 dark:text-blue-400">
                    Undo to here
                </button>
            </div>

            @include('matches.partials.game-completed-modal')
        </div>
    @endif

</x-layouts.admin>
