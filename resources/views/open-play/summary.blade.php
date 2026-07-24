@php
    $room = $summary['room'];
    $ranked = $summary['players']->sortByDesc('wins')->values();
@endphp

<x-layouts.app :title="$room->title.' — summary'" :hide-footer="true">

    <section class="mx-auto max-w-2xl px-4 py-14 sm:px-6 lg:px-8">
        <a href="{{ route('open-play.show', $room) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition-colors hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left"></i>
            {{ $room->title }}
        </a>

        <div class="mt-4 flex items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                <i class="ph ph-flag-checkered text-xl"></i>
            </span>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
                    {{ $room->title }}
                </h1>
                <p class="text-sm text-ink-500 dark:text-ink-400">Session summary</p>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                <p class="font-display text-2xl font-semibold text-ink-950 dark:text-white">{{ $summary['total_players'] }}</p>
                <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Players</p>
            </div>
            <div class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                <p class="font-display text-2xl font-semibold text-ink-950 dark:text-white">{{ $summary['total_matches'] }}</p>
                <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Matches played</p>
            </div>
            <div class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                <p class="font-display text-2xl font-semibold text-ink-950 dark:text-white">
                    {{ $summary['duration_minutes'] !== null ? $summary['duration_minutes'].'m' : '—' }}
                </p>
                <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Duration</p>
            </div>
        </div>

        <div class="mt-8">
            <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">Players</h2>
            <div class="mt-3 space-y-1.5">
                @forelse ($ranked as $index => $player)
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-ink-100 px-3.5 py-2.5 text-sm dark:border-ink-800">
                        <span class="flex items-center gap-2.5">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold {{ $index === 0 ? 'bg-accent-100 text-accent-800 dark:bg-accent-950 dark:text-accent-300' : 'bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400' }}">
                                {{ $index === 0 ? '★' : $index + 1 }}
                            </span>
                            <span class="font-medium text-ink-900 dark:text-ink-100">{{ $player['name'] }}</span>
                        </span>
                        <span class="shrink-0 text-ink-500 dark:text-ink-400">
                            {{ $player['games_played'] }} games &middot;
                            <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ $player['wins'] }}W</span>-<span class="font-medium text-rose-500 dark:text-rose-400">{{ $player['losses'] }}L</span>
                        </span>
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-ink-200 px-3.5 py-4 text-center text-sm text-ink-400 dark:border-ink-800">
                        No players joined this session.
                    </p>
                @endforelse
            </div>
        </div>

        <div class="mt-8">
            <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">Matches</h2>
            <div class="mt-3 space-y-2">
                @forelse ($summary['matches'] as $match)
                    @php
                        $viewerId = auth()->id();
                        $viewerTeam = $match['team_a']->contains('user_id', $viewerId) ? 1 : ($match['team_b']->contains('user_id', $viewerId) ? 2 : null);
                        $viewerWon = $viewerTeam !== null && $viewerTeam === $match['winner_team'];
                    @endphp
                    <div class="rounded-xl border p-3.5 text-sm {{ $viewerTeam ? ($viewerWon ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900 dark:bg-emerald-950/30' : 'border-rose-200 bg-rose-50/50 dark:border-rose-900 dark:bg-rose-950/30') : 'border-ink-100 dark:border-ink-800' }}">
                        <div class="flex items-center justify-between text-xs text-ink-500 dark:text-ink-400">
                            <span>Round {{ $match['round_number'] }} &middot; {{ $match['court_name'] }}</span>
                            @if ($viewerTeam)
                                <span class="font-semibold {{ $viewerWon ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500 dark:text-rose-400' }}">
                                    {{ $viewerWon ? 'You won' : 'You lost' }}
                                </span>
                            @endif
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <div class="flex-1 text-right {{ $match['winner_team'] === 1 ? 'font-semibold text-ink-950 dark:text-white' : 'text-ink-500 dark:text-ink-400' }}">
                                @foreach ($match['team_a'] as $p)
                                    <div>{{ $p['name'] }}{{ $p['user_id'] === $viewerId ? ' (you)' : '' }}</div>
                                @endforeach
                            </div>
                            <span class="shrink-0 text-[11px] font-semibold text-ink-400">vs</span>
                            <div class="flex-1 text-left {{ $match['winner_team'] === 2 ? 'font-semibold text-ink-950 dark:text-white' : 'text-ink-500 dark:text-ink-400' }}">
                                @foreach ($match['team_b'] as $p)
                                    <div>{{ $p['name'] }}{{ $p['user_id'] === $viewerId ? ' (you)' : '' }}</div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-ink-200 px-3.5 py-4 text-center text-sm text-ink-400 dark:border-ink-800">
                        No matches were completed in this session.
                    </p>
                @endforelse
            </div>
        </div>
    </section>

</x-layouts.app>
