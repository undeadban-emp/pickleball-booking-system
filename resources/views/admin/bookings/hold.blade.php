<x-layouts.admin :title="'Hold '.$booking->booking_code">

    @php
        $currentFirst = $booking->slots->sortBy('start_time')->first();
        $currentLast = $booking->slots->sortBy('start_time')->last();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                <i class="ph ph-pause-circle text-xl"></i>
            </span>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Hold {{ $booking->booking_code }}</h1>
                <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">Frees the selected hour(s) for other customers right away — no new date/time picked yet.</p>
            </div>
        </div>
        <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left text-base"></i>
            Back to bookings
        </a>
    </div>

    {{-- Currently --}}
    <div class="mt-5 flex flex-wrap items-center gap-4 rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400">
            <i class="ph ph-user text-xl"></i>
        </div>
        <div class="min-w-0">
            <p class="text-[11px] font-semibold tracking-wide text-ink-400 uppercase">Currently booked</p>
            <p class="mt-0.5 font-medium text-ink-950 dark:text-white">{{ $booking->contactName() }} &middot; {{ $booking->court->name }}</p>
        </div>
        @if ($currentFirst)
            <div class="ml-auto flex items-center gap-2 rounded-xl bg-ink-50 px-3 py-2 dark:bg-ink-950/60">
                <i class="ph ph-calendar text-ink-400"></i>
                <div>
                    <p class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ \Illuminate\Support\Carbon::parse($currentFirst->slot_date)->format('D, M j, Y') }}</p>
                    <p class="text-xs text-ink-500 dark:text-ink-400">{{ \Illuminate\Support\Carbon::parse($currentFirst->start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($currentLast->end_time)->format('g:i A') }}</p>
                </div>
            </div>
        @endif
    </div>

    @error('affected_court_slot_ids')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            {{ $message }}
        </div>
    @enderror

    <form
        method="POST"
        action="{{ route('admin.bookings.hold.store', $booking) }}"
        class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px] lg:items-start"
        x-data="holdSlotsForm({
            bookingSlots: @js($booking->slots->sortBy('start_time')->values()->map(fn ($s) => ['id' => $s->id, 'start_time' => $s->start_time, 'end_time' => $s->end_time])),
        })"
    >
        @csrf

        <div class="space-y-4">
            @include('admin.bookings.partials.affected-hours-picker', ['booking' => $booking, 'verb' => 'hold'])
        </div>

        <div class="space-y-4 lg:sticky lg:top-6">
            <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                    <i class="ph ph-note text-sm"></i> Reason
                    <span class="font-normal normal-case text-ink-400">(optional)</span>
                </p>
                <input name="reason" type="text" placeholder="e.g. Rain" value="{{ old('reason') }}"
                    class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
            </div>

            <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                    <i class="ph ph-pause-circle text-sm"></i> On hold
                </p>
                <p class="mt-2 text-sm text-ink-500 dark:text-ink-400" x-show="affectedIds.length === 0">
                    The whole booking will be put on hold.
                </p>
                <p class="mt-2 text-sm text-ink-500 dark:text-ink-400" x-show="affectedIds.length > 0" x-cloak>
                    <span x-text="affectedIds.length"></span> selected hour(s) will be put on hold — the rest stays booked as-is.
                </p>
                <p class="mt-2 text-xs text-ink-400">Held hours free up for other customers immediately. Reschedule from the Held Bookings list whenever you're ready.</p>

                <button
                    type="submit"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-ink-950 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400"
                >
                    <i class="ph ph-pause-circle"></i>
                    <span x-text="affectedIds.length > 0 ? 'Put ' + affectedIds.length + ' selected hour(s) on hold' : 'Put whole booking on hold'"></span>
                </button>
            </div>
        </div>
    </form>

</x-layouts.admin>
