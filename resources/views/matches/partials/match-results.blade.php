@php
    $standalone = $standalone ?? false;
    $winnerName = $match->winner_team ? $match->teamName($match->winner_team) : null;
    $summaryLines = collect();
    $summaryLines->push($match->event_name ?: 'Match results');
    $summaryLines->push($match->teamName(1).' vs '.$match->teamName(2));
    foreach ($match->games->sortBy('game_number') as $g) {
        $summaryLines->push("Game {$g->game_number}: {$g->team1_score}-{$g->team2_score}");
    }
    if ($winnerName) {
        $summaryLines->push("Winner: {$winnerName}");
    }
    $summaryText = $summaryLines->implode("\n");
@endphp

<div
    x-data="{ copied: false, copy() { navigator.clipboard.writeText(@js($summaryText)); this.copied = true; setTimeout(() => this.copied = false, 2000); } }"
    class="rounded-2xl border border-ink-200 bg-white p-6 dark:border-ink-800 dark:bg-ink-900"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">{{ $match->event_name ?: 'Match results' }}</p>
            <h2 class="mt-1 font-display text-xl font-semibold text-ink-950 dark:text-white">
                {{ $match->teamName(1) }} <span class="text-ink-400">vs</span> {{ $match->teamName(2) }}
            </h2>
            @if ($match->referee_name || $match->location)
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                    {{ collect([$match->referee_name ? 'Ref: '.$match->referee_name : null, $match->location])->filter()->implode(' · ') }}
                </p>
            @endif
        </div>

        <button type="button" @click="copy()" class="flex shrink-0 items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3 py-1.5 text-xs font-semibold text-ink-700 transition-colors hover:border-accent-400 dark:border-ink-700 dark:bg-ink-950 dark:text-ink-200">
            <i class="ph" :class="copied ? 'ph-check text-accent-600 dark:text-accent-400' : 'ph-copy'"></i>
            <span x-text="copied ? 'Copied' : 'Copy summary'"></span>
        </button>
    </div>

    @if ($winnerName)
        <div class="mt-4 flex items-center gap-2 rounded-xl border border-accent-300 bg-accent-50 px-4 py-3 dark:border-accent-800 dark:bg-accent-950">
            <i class="ph ph-trophy text-lg text-accent-700 dark:text-accent-400"></i>
            <p class="text-sm font-semibold text-accent-800 dark:text-accent-200">{{ $winnerName }} won the match</p>
        </div>
    @endif

    <div class="mt-4 overflow-hidden rounded-xl border border-ink-100 dark:border-ink-800">
        <table class="w-full text-sm">
            <thead class="bg-ink-50 text-xs font-semibold tracking-wide text-ink-500 uppercase dark:bg-ink-800/50 dark:text-ink-400">
                <tr>
                    <th class="px-4 py-2 text-left">Game</th>
                    <th class="px-4 py-2 text-right">{{ $match->teamName(1) }}</th>
                    <th class="px-4 py-2 text-right">{{ $match->teamName(2) }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                @foreach ($match->games->sortBy('game_number') as $g)
                    <tr>
                        <td class="px-4 py-2 text-ink-600 dark:text-ink-400">Game {{ $g->game_number }}</td>
                        <td class="px-4 py-2 text-right font-semibold {{ $g->winner_team === 1 ? 'text-accent-700 dark:text-accent-400' : 'text-ink-900 dark:text-ink-100' }}">{{ $g->team1_score }}</td>
                        <td class="px-4 py-2 text-right font-semibold {{ $g->winner_team === 2 ? 'text-accent-700 dark:text-accent-400' : 'text-ink-900 dark:text-ink-100' }}">{{ $g->team2_score }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        @if (! $standalone && $match->status === 'verifying')
            <form method="POST" action="{{ route('admin.matches.complete', $match) }}">
                @csrf
                <button type="submit" class="rounded-full bg-ink-950 px-6 py-3 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                    Save &amp; return to booking
                </button>
            </form>
        @else
            <a href="{{ route('admin.bookings.index') }}" class="rounded-full border border-ink-200 px-6 py-3 text-sm font-semibold text-ink-700 hover:border-ink-400 dark:border-ink-700 dark:text-ink-200">
                Back to bookings
            </a>
        @endif
    </div>
</div>
