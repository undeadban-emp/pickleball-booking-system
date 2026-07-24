<x-layouts.admin :title="'Held Bookings'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Held Bookings</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Hours put on hold (e.g. rain) — freed up for other customers, waiting on a new date/time for the original customer.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 space-y-3">
        @forelse ($holds as $hold)
            <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                            <i class="ph ph-pause-circle text-xl"></i>
                        </span>
                        <div>
                            <p class="font-medium text-ink-950 dark:text-white">{{ $hold->booking->contactName() }} &middot; {{ $hold->fromCourt->name }}</p>
                            <p class="mt-0.5 text-xs font-mono text-ink-500 dark:text-ink-400">{{ $hold->booking->booking_code }}</p>
                            <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">
                                Was {{ $hold->from_slot_date->format('D, M j, Y') }}, {{ \Illuminate\Support\Carbon::parse($hold->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($hold->from_end_time)->format('g:i A') }}
                            </p>
                            @if ($hold->reason)
                                <p class="mt-1 flex items-center gap-1.5 text-xs text-ink-500 dark:text-ink-400">
                                    <i class="ph ph-note text-sm"></i> {{ $hold->reason }}
                                </p>
                            @endif
                            <p class="mt-1 text-xs text-ink-400">
                                Held by {{ $hold->heldBy->name ?? 'system' }} &middot; {{ $hold->created_at->format('M j, g:i A') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a
                            href="{{ route('admin.bookings.reschedule.edit', $hold->booking) }}"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-accent-400 hover:text-accent-700 dark:border-ink-700 dark:text-ink-300 dark:hover:text-accent-400"
                        >
                            <i class="ph ph-arrow-clockwise text-base"></i>
                            Reschedule
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-ink-200 p-10 text-center dark:border-ink-800">
                <i class="ph ph-pause-circle text-3xl text-ink-300 dark:text-ink-700"></i>
                <p class="mt-2 text-sm text-ink-500 dark:text-ink-400">No bookings are currently on hold.</p>
            </div>
        @endforelse
    </div>

</x-layouts.admin>
