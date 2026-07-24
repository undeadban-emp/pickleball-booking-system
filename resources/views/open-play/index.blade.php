<x-layouts.app :title="'Open Play'" :hide-footer="true">

    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl font-semibold tracking-tight text-ink-950 dark:text-white">
                    Open Play
                </h1>
                <p class="mt-1.5 max-w-md text-sm text-ink-500 dark:text-ink-400">
                    Already booked a court? Turn it into a room and let players rotate in for fair doubles matches.
                </p>
            </div>
            @auth
                <a href="{{ route('open-play.create') }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-accent-500 px-4 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400">
                    <i class="ph ph-plus text-base"></i>
                    Open a room
                </a>
            @endauth
        </div>

        @auth
            <a href="{{ route('open-play.history') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition-colors hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
                <i class="ph ph-trophy"></i>
                My rank and match history
                <i class="ph ph-arrow-right text-xs"></i>
            </a>
        @endauth

        @if ($rooms->isEmpty())
            <div class="mt-10 flex flex-col items-center rounded-2xl border border-dashed border-ink-200 px-6 py-14 text-center dark:border-ink-800">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-400 dark:bg-ink-800 dark:text-ink-500">
                    <i class="ph ph-users-three text-2xl"></i>
                </span>
                <p class="mt-4 text-sm font-medium text-ink-700 dark:text-ink-300">No open rooms right now.</p>
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Once someone opens a room from their booking, it will show up here.</p>
            </div>
        @else
            <div class="mt-8 space-y-3">
                @foreach ($rooms as $room)
                    <a href="{{ route('open-play.show', $room) }}" class="flex items-center justify-between gap-4 rounded-2xl border p-4 transition-colors {{ $room->joined ?? false ? 'border-accent-300 bg-accent-50/60 hover:border-accent-400 dark:border-accent-800 dark:bg-accent-950/30' : 'border-ink-100 bg-white hover:border-accent-400 dark:border-ink-800 dark:bg-ink-900' }}">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-ink-100 text-sm font-semibold text-ink-700 dark:bg-ink-800 dark:text-ink-200">
                                {{ strtoupper(substr($room->title, 0, 1)) }}
                            </span>
                            <div class="min-w-0">
                                <p class="flex items-center gap-2 font-medium text-ink-950 dark:text-white">
                                    <span class="min-w-0 truncate">{{ $room->title }}</span>
                                    @if ($room->joined ?? false)
                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-accent-500 px-2 py-0.5 text-[10px] font-semibold text-white">
                                            <i class="ph ph-check-circle"></i> Joined
                                        </span>
                                    @endif
                                </p>
                                <p class="mt-0.5 truncate text-sm text-ink-500 dark:text-ink-400">
                                    {{ \Illuminate\Support\Carbon::parse($room->session_date)->format('M j, Y') }},
                                    {{ \Illuminate\Support\Carbon::parse($room->start_time)->format('g:i A') }}
                                    &middot; hosted by {{ $room->host->name }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1.5">
                            <x-open-play.room-status-badge :status="$room->status" />
                            <span class="text-xs font-medium text-ink-400">
                                {{ $room->players_count }}/{{ $room->max_players }} players
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ $room->checked_in_count }} checked in
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $rooms->links() }}
            </div>
        @endif
    </section>

</x-layouts.app>
