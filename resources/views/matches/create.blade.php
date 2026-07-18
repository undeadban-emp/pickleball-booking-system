<x-layouts.admin :title="'New Match'">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left"></i>
            Bookings
        </a>
    </div>

    <h1 class="mt-2 font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">New match</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
        {{ $booking->booking_code }} · {{ $booking->court->name }} · {{ $booking->contactName() }}
    </p>

    @error('match')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            {{ $message }}
        </div>
    @enderror

    <form
        method="POST"
        action="{{ route('admin.matches.store', $booking) }}"
        class="mt-6 grid grid-cols-1 gap-6"
        x-data="{ matchType: '{{ old('match_type', 'doubles') }}' }"
    >
        @csrf

        {{-- Match type + first server --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Match type</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border p-3 text-sm font-semibold transition-colors has-checked:border-accent-500 has-checked:bg-accent-50 dark:border-ink-700 dark:has-checked:bg-accent-950">
                    <input type="radio" name="match_type" value="doubles" x-model="matchType" class="text-accent-600 focus:ring-accent-500">
                    Doubles
                </label>
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border p-3 text-sm font-semibold transition-colors has-checked:border-accent-500 has-checked:bg-accent-50 dark:border-ink-700 dark:has-checked:bg-accent-950">
                    <input type="radio" name="match_type" value="singles" x-model="matchType" class="text-accent-600 focus:ring-accent-500">
                    Singles
                </label>
            </div>
            @error('match_type')
                <p class="mt-2 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror

            <p class="mt-5 text-sm font-semibold text-ink-950 dark:text-white">First server</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border p-3 text-sm font-semibold transition-colors has-checked:border-accent-500 has-checked:bg-accent-50 dark:border-ink-700 dark:has-checked:bg-accent-950">
                    <input type="radio" name="serving_team_first" value="1" checked class="text-accent-600 focus:ring-accent-500">
                    Team 1 serves first
                </label>
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border p-3 text-sm font-semibold transition-colors has-checked:border-accent-500 has-checked:bg-accent-50 dark:border-ink-700 dark:has-checked:bg-accent-950">
                    <input type="radio" name="serving_team_first" value="2" class="text-accent-600 focus:ring-accent-500">
                    Team 2 serves first
                </label>
            </div>
            @error('serving_team_first')
                <p class="mt-2 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Players --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Players</p>

            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                {{-- Team 1 --}}
                <div class="space-y-3">
                    <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Team 1</p>

                    <div class="flex gap-2">
                        <input type="hidden" name="players[0][team]" value="1">
                        <input type="text" name="players[0][name]" value="{{ old('players.0.name') }}" required maxlength="80" placeholder="Player 1 name"
                            class="flex-1 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                        <select name="players[0][gender]" class="w-20 rounded-lg border border-ink-200 bg-white px-2 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                            <option value="unknown">?</option>
                            <option value="f">F</option>
                            <option value="m">M</option>
                            <option value="x">X</option>
                        </select>
                    </div>

                    <div class="flex gap-2" x-show="matchType === 'doubles'">
                        <input type="hidden" name="players[1][team]" value="1">
                        <input type="text" name="players[1][name]" value="{{ old('players.1.name') }}" :required="matchType === 'doubles'" :disabled="matchType !== 'doubles'" maxlength="80" placeholder="Player 2 name"
                            class="flex-1 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                        <select name="players[1][gender]" :disabled="matchType !== 'doubles'" class="w-20 rounded-lg border border-ink-200 bg-white px-2 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                            <option value="unknown">?</option>
                            <option value="f">F</option>
                            <option value="m">M</option>
                            <option value="x">X</option>
                        </select>
                    </div>
                </div>

                {{-- Team 2 --}}
                <div class="space-y-3">
                    <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Team 2</p>

                    <div class="flex gap-2">
                        <input type="hidden" name="players[2][team]" value="2">
                        <input type="text" name="players[2][name]" value="{{ old('players.2.name') }}" required maxlength="80" placeholder="Player 1 name"
                            class="flex-1 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                        <select name="players[2][gender]" class="w-20 rounded-lg border border-ink-200 bg-white px-2 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                            <option value="unknown">?</option>
                            <option value="f">F</option>
                            <option value="m">M</option>
                            <option value="x">X</option>
                        </select>
                    </div>

                    <div class="flex gap-2" x-show="matchType === 'doubles'">
                        <input type="hidden" name="players[3][team]" value="2">
                        <input type="text" name="players[3][name]" value="{{ old('players.3.name') }}" :required="matchType === 'doubles'" :disabled="matchType !== 'doubles'" maxlength="80" placeholder="Player 2 name"
                            class="flex-1 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                        <select name="players[3][gender]" :disabled="matchType !== 'doubles'" class="w-20 rounded-lg border border-ink-200 bg-white px-2 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                            <option value="unknown">?</option>
                            <option value="f">F</option>
                            <option value="m">M</option>
                            <option value="x">X</option>
                        </select>
                    </div>
                </div>
            </div>
            @error('players')
                <p class="mt-3 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Game rules --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Game rules</p>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Best of</label>
                    <select name="best_of" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                        @foreach ([1, 3, 5] as $n)
                            <option value="{{ $n }}" @selected(old('best_of', 3) == $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Games to</label>
                    <select name="games_to" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                        @foreach ([11, 15, 21] as $n)
                            <option value="{{ $n }}" @selected(old('games_to', 11) == $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Winner rule</label>
                    <select name="win_rule" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                        <option value="win_by_2" @selected(old('win_rule', 'win_by_2') === 'win_by_2')>Win by 2</option>
                        <option value="win_by_1" @selected(old('win_rule') === 'win_by_1')>Win by 1</option>
                        <option value="first_to" @selected(old('win_rule') === 'first_to')>First to</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Time-outs / game</label>
                    <select name="timeouts_per_game" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                        @foreach ([0, 1, 2, 3] as $n)
                            <option value="{{ $n }}" @selected(old('timeouts_per_game', 2) == $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <p class="mt-4 text-sm font-semibold text-ink-950 dark:text-white">Scoring type</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <label class="flex cursor-pointer flex-col gap-1 rounded-xl border p-3 transition-colors has-checked:border-accent-500 has-checked:bg-accent-50 dark:border-ink-700 dark:has-checked:bg-accent-950">
                    <span class="flex items-center gap-2 text-sm font-semibold text-ink-900 dark:text-ink-100">
                        <input type="radio" name="scoring_type" value="service" checked class="text-accent-600 focus:ring-accent-500">
                        Service point
                    </span>
                    <span class="text-xs text-ink-500 dark:text-ink-400">Traditional rule — only the serving side can score.</span>
                </label>
                <label class="flex cursor-pointer flex-col gap-1 rounded-xl border p-3 transition-colors has-checked:border-accent-500 has-checked:bg-accent-50 dark:border-ink-700 dark:has-checked:bg-accent-950">
                    <span class="flex items-center gap-2 text-sm font-semibold text-ink-900 dark:text-ink-100">
                        <input type="radio" name="scoring_type" value="rally" class="text-accent-600 focus:ring-accent-500">
                        Rally point
                    </span>
                    <span class="text-xs text-ink-500 dark:text-ink-400">Every rally scores, regardless of who served.</span>
                </label>
            </div>
        </div>

        {{-- Event details --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Event details <span class="font-normal text-ink-400">(optional)</span></p>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <input type="text" name="event_name" value="{{ old('event_name') }}" maxlength="120" placeholder="Event name"
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input type="text" name="referee_name" value="{{ old('referee_name') }}" maxlength="120" placeholder="Referee name"
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input type="text" name="location" value="{{ old('location') }}" maxlength="150" placeholder="Location"
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input type="email" name="email_results_to" value="{{ old('email_results_to') }}" maxlength="255" placeholder="Email results to"
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
            </div>
        </div>

        <button type="submit" class="w-fit rounded-full bg-ink-950 px-6 py-3 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
            Create match &amp; continue
        </button>
    </form>

</x-layouts.admin>
