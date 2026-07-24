<x-layouts.app :title="$profileUser->name.' — Open Play'" :hide-footer="true">

    <section class="mx-auto max-w-2xl px-4 py-14 sm:px-6 lg:px-8">
        <a href="{{ route('open-play.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition-colors hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left"></i>
            Open Play
        </a>

        <div class="mt-4 flex items-center gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-ink-950 text-sm font-semibold text-white dark:bg-accent-500 dark:text-ink-950">
                {{ strtoupper(substr($profileUser->name, 0, 1)) }}
            </span>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
                {{ $profileUser->name }}
            </h1>
        </div>

        <div class="mt-6 rounded-2xl border border-ink-100 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <div class="flex items-center justify-between">
                <x-open-play.rank-badge :rank="$stat->rank" class="text-sm! px-3! py-1!" />
                <p class="text-sm text-ink-500 dark:text-ink-400">{{ $stat->total_games }} games played</p>
            </div>

            <div class="mt-4">
                <div class="flex items-center justify-between text-xs text-ink-500 dark:text-ink-400">
                    <span>Win rate</span>
                    <span class="font-semibold text-ink-800 dark:text-ink-200">{{ $stat->win_rate }}%</span>
                </div>
                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                    <div class="h-full rounded-full bg-accent-500" style="width: {{ min(100, max(0, $stat->win_rate)) }}%"></div>
                </div>
            </div>

            <div class="mt-4 flex divide-x divide-ink-100 border-t border-ink-100 pt-4 text-center dark:divide-ink-800 dark:border-ink-800">
                <div class="flex-1">
                    <p class="font-display text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ $stat->total_wins }}</p>
                    <p class="text-xs text-ink-500 dark:text-ink-400">Wins</p>
                </div>
                <div class="flex-1">
                    <p class="font-display text-lg font-semibold text-rose-500 dark:text-rose-400">{{ $stat->total_losses }}</p>
                    <p class="text-xs text-ink-500 dark:text-ink-400">Losses</p>
                </div>
            </div>
        </div>

        <div class="mt-8">
            <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">Recent matches</h2>
            <div class="mt-3 space-y-1.5">
                @forelse ($matches as $matchPlayer)
                    @php
                        $match = $matchPlayer->match;
                        $won = $match->winner_team === $matchPlayer->team;
                    @endphp
                    <div class="flex items-center gap-3 rounded-xl border border-ink-100 p-3.5 text-sm dark:border-ink-800">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $won ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' }}">
                            <i class="ph {{ $won ? 'ph-trophy' : 'ph-x' }} text-base"></i>
                        </span>
                        <p class="min-w-0 flex-1 truncate font-medium text-ink-900 dark:text-ink-100">{{ $match->room->title }}</p>
                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $won ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' }}">
                            {{ $won ? 'Won' : 'Lost' }}
                        </span>
                    </div>
                @empty
                    <div class="flex flex-col items-center rounded-2xl border border-dashed border-ink-200 px-6 py-12 text-center dark:border-ink-800">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-400 dark:bg-ink-800 dark:text-ink-500">
                            <i class="ph ph-trophy text-2xl"></i>
                        </span>
                        <p class="mt-4 text-sm text-ink-500 dark:text-ink-400">No Open Play matches yet.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

</x-layouts.app>
