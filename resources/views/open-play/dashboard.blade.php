<x-layouts.app :title="$room->title.' — live'" :hide-footer="true">

    <section
        class="mx-auto max-w-3xl px-4 py-14 sm:px-6 lg:px-8"
        x-data="openPlayDashboard({
            pollUrl: '{{ route('open-play.dashboard.poll', $room) }}',
            completeUrlTemplate: '{{ route('open-play.matches.complete', [$room, '__MATCH__']) }}',
            isHost: {{ $room->host_user_id === auth()->id() ? 'true' : 'false' }},
        })"
    >
        <a href="{{ route('open-play.show', $room) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition-colors hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left"></i>
            {{ $room->title }}
        </a>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <h1 class="font-display text-3xl font-semibold tracking-tight text-ink-950 dark:text-white">
                    Live dashboard
                </h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                    Round <span x-text="state.room?.current_round_number ?? {{ $room->current_round_number }}"></span>
                </span>
            </div>

            @if ($room->host_user_id === auth()->id())
                <form method="POST" action="{{ route('open-play.end', $room) }}" onsubmit="return confirmSubmit(this, {title: 'End session?', text: 'This will finish the room and cancel any matches still in progress.'})">
                    @csrf
                    <button type="submit" class="rounded-full border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 transition-colors hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-200">
                        End session
                    </button>
                </form>
            @endif
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <template x-for="court in state.courts" :key="court.court_id">
                <div class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1.5 text-sm font-semibold text-ink-900 dark:text-ink-100">
                            <i class="ph ph-map-pin text-ink-400"></i>
                            <span x-text="court.court_name"></span>
                        </p>
                        <span x-show="court.current_match" x-cloak class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Playing
                        </span>
                    </div>

                    <template x-if="court.current_match">
                        <div class="mt-4">
                            <div class="flex flex-wrap items-center justify-center gap-1.5">
                                <template x-for="p in court.current_match.team_a" :key="p.user_id">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-ink-100 py-1 pr-2.5 pl-1 text-xs font-medium text-ink-800 dark:bg-ink-800 dark:text-ink-200">
                                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white text-[9px] font-bold text-ink-600 dark:bg-ink-950 dark:text-ink-300" x-text="p.name.charAt(0).toUpperCase()"></span>
                                        <span x-text="p.name"></span>
                                        <span class="rounded-full px-1.5 py-0.5 text-[9px] font-semibold" :class="rankClass(p.rank)" x-text="p.rank"></span>
                                    </span>
                                </template>
                            </div>

                            <p class="my-2 text-center text-[11px] font-semibold tracking-wide text-ink-400 uppercase">vs</p>

                            <div class="flex flex-wrap items-center justify-center gap-1.5">
                                <template x-for="p in court.current_match.team_b" :key="p.user_id">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-ink-100 py-1 pr-2.5 pl-1 text-xs font-medium text-ink-800 dark:bg-ink-800 dark:text-ink-200">
                                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white text-[9px] font-bold text-ink-600 dark:bg-ink-950 dark:text-ink-300" x-text="p.name.charAt(0).toUpperCase()"></span>
                                        <span x-text="p.name"></span>
                                        <span class="rounded-full px-1.5 py-0.5 text-[9px] font-semibold" :class="rankClass(p.rank)" x-text="p.rank"></span>
                                    </span>
                                </template>
                            </div>

                            <template x-if="isHost">
                                <div class="mt-4 flex gap-2">
                                    <button type="button" class="flex-1 rounded-full bg-accent-500 px-3 py-2 text-xs font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400" x-on:click="completeMatch(court.current_match.id, 1)">
                                        Team A wins
                                    </button>
                                    <button type="button" class="flex-1 rounded-full bg-accent-500 px-3 py-2 text-xs font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400" x-on:click="completeMatch(court.current_match.id, 2)">
                                        Team B wins
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="!court.current_match">
                        <div class="mt-4 flex flex-col items-center py-4 text-center">
                            <i class="ph ph-hourglass-medium text-xl text-ink-300 dark:text-ink-600"></i>
                            <p class="mt-2 text-sm text-ink-400">Waiting for next match&hellip;</p>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="state.courts.length === 0">
                <div class="rounded-2xl border border-dashed border-ink-200 p-8 text-center text-sm text-ink-400 dark:border-ink-800 sm:col-span-2">
                    Loading courts&hellip;
                </div>
            </template>
        </div>

        <div class="mt-8">
            <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">Waiting queue</h2>
            <div class="mt-3 space-y-1.5">
                <template x-for="p in state.waiting" :key="p.user_id">
                    <div class="flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-sm" :class="p.checked_in ? 'border-ink-100 dark:border-ink-800' : 'border-dashed border-ink-200 opacity-60 dark:border-ink-700'">
                        <span class="flex items-center gap-2.5 text-ink-800 dark:text-ink-200">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-ink-100 text-[10px] font-semibold text-ink-700 dark:bg-ink-800 dark:text-ink-200" x-text="p.name.charAt(0).toUpperCase()"></span>
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="p.checked_in ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                            <span x-text="p.name"></span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="rankClass(p.rank)" x-text="p.rank"></span>
                            <span x-show="!p.checked_in" x-cloak class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-300">Not checked in</span>
                        </span>
                        <span class="shrink-0 text-xs text-ink-400" x-text="p.games_played + ' games'"></span>
                    </div>
                </template>
                <template x-if="state.waiting.length === 0">
                    <p class="rounded-xl border border-dashed border-ink-200 px-3.5 py-4 text-center text-sm text-ink-400 dark:border-ink-800">
                        Everyone is currently playing.
                    </p>
                </template>
            </div>
        </div>
    </section>

</x-layouts.app>
