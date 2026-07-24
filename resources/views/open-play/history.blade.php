<x-layouts.app :title="'My Open Play history'" :hide-footer="true">

    <section class="mx-auto max-w-2xl px-4 py-14 sm:px-6 lg:px-8">
        <a href="{{ route('open-play.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition-colors hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left"></i>
            Open Play
        </a>

        <h1 class="mt-4 font-display text-3xl font-semibold tracking-tight text-ink-950 dark:text-white">
            My rank
        </h1>

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
            <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">Sessions played</h2>
            <div class="mt-3 space-y-2">
                @forelse ($rooms as $room)
                    @php
                        $record = $records->get($room->id);
                        $isHost = $room->host_user_id === auth()->id();
                        $destination = $room->status === 'finished'
                            ? route('open-play.summary', $room)
                            : route('open-play.show', $room);
                    @endphp
                    <a href="{{ $destination }}" class="flex items-center gap-3 rounded-xl border border-ink-100 p-3.5 text-sm transition-colors hover:border-accent-400 dark:border-ink-800">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-ink-100 text-sm font-semibold text-ink-700 dark:bg-ink-800 dark:text-ink-200">
                            {{ strtoupper(substr($room->title, 0, 1)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 truncate font-medium text-ink-950 dark:text-white">
                                {{ $room->title }}
                                @if ($isHost)
                                    <span class="rounded-full bg-ink-100 px-1.5 py-0.5 text-[10px] font-semibold text-ink-500 dark:bg-ink-800 dark:text-ink-400">Host</span>
                                @endif
                            </p>
                            <p class="mt-0.5 text-ink-500 dark:text-ink-400">
                                {{ \Illuminate\Support\Carbon::parse($room->session_date)->format('M j, Y') }}
                                @if ($record)
                                    &middot; {{ $record->games }} {{ \Illuminate\Support\Str::plural('game', $record->games) }},
                                    <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ $record->wins }}W</span>-<span class="font-medium text-rose-500 dark:text-rose-400">{{ $record->games - $record->wins }}L</span>
                                @endif
                            </p>
                        </div>
                        <x-open-play.room-status-badge :status="$room->status" class="shrink-0" />
                    </a>
                @empty
                    <div class="flex flex-col items-center rounded-2xl border border-dashed border-ink-200 px-6 py-12 text-center dark:border-ink-800">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-400 dark:bg-ink-800 dark:text-ink-500">
                            <i class="ph ph-trophy text-2xl"></i>
                        </span>
                        <p class="mt-4 text-sm text-ink-500 dark:text-ink-400">You haven't played an Open Play session yet.</p>
                        <a href="{{ route('open-play.index') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-4 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400">
                            Browse rooms
                            <i class="ph ph-arrow-right text-base"></i>
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="mt-6">
            {{ $rooms->links() }}
        </div>
    </section>

</x-layouts.app>
