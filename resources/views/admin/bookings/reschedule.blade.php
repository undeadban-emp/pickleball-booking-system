<x-layouts.admin :title="'Reschedule '.$booking->booking_code">

    @php
        $currentFirst = $booking->slots->sortBy('start_time')->first();
        $currentLast = $booking->slots->sortBy('start_time')->last();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-100 text-accent-700 dark:bg-accent-950 dark:text-accent-400">
                <i class="ph ph-arrow-clockwise text-xl"></i>
            </span>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Reschedule {{ $booking->booking_code }}</h1>
                <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">Moves this session to a new date/time — same booking, same customer, no new payment.</p>
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

    @error('court_slot_ids')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            {{ $message }}
        </div>
    @enderror
    @error('affected_court_slot_ids')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            {{ $message }}
        </div>
    @enderror

    <form
        method="POST"
        :action="isPartial ? '{{ route('admin.bookings.split-reschedule.update', $booking) }}' : '{{ route('admin.bookings.reschedule.update', $booking) }}'"
        class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_360px] lg:items-start"
        x-data="adminBookingForm({
            courts: @js($courts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'status' => $c->status])),
            slotsUrlBase: '{{ url('/api/courts') }}',
            periodBoundaries: @js($periodBoundaries),
            periodEnds: @js($periodEnds),
            initialCourtId: {{ $booking->court_id }},
            expectedHours: {{ $booking->slots->count() }},
            singleSession: true,
            bookingSlots: @js($booking->slots->sortBy('start_time')->values()->map(fn ($s) => ['id' => $s->id, 'start_time' => $s->start_time, 'end_time' => $s->end_time])),
        })"
    >
        @csrf

        <div class="space-y-4">
            @if ($booking->slots->count() > 1)
                <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                    <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                        <i class="ph ph-selection-slash text-sm"></i> Which hours were affected?
                    </p>
                    <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Leave unselected to move the whole session. Tap just the affected hour(s) to move only those — the rest stays booked as-is.</p>

                    <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                        <template x-for="slot in allBookingSlots" :key="slot.id">
                            <button
                                type="button"
                                @click="toggleAffected(slot.id)"
                                class="rounded-lg border px-2 py-2.5 text-xs font-semibold transition-all"
                                :class="affected[slot.id] ? 'border-rose-500 bg-rose-500 text-white shadow-sm scale-[1.02]' : 'border-ink-200 bg-ink-50 text-ink-600 hover:border-rose-300 hover:bg-rose-50 dark:border-ink-700 dark:bg-ink-950 dark:text-ink-300'"
                                x-text="slotLabel(slot)"
                            ></button>
                        </template>
                    </div>

                    <p x-show="isPartial" x-cloak class="mt-3 flex items-center gap-1.5 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                        <i class="ph ph-info"></i>
                        Only the <span x-text="affectedIds.length"></span> selected hour(s) will move — the rest stays on this booking untouched.
                    </p>

                    <template x-for="id in affectedIds" :key="'affected-' + id">
                        <input type="hidden" name="affected_court_slot_ids[]" :value="id">
                    </template>
                </div>
            @endif

            <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                    <i class="ph ph-map-pin text-sm"></i> Where to
                </p>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Court</label>
                        <select name="court_id" x-model="courtId" @change="onCourtChange()" required
                            class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                            @foreach ($courts as $court)
                                <option value="{{ $court->id }}">{{ $court->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-ink-500 dark:text-ink-400">New date</label>
                        <input type="date" x-model="dateStr" :min="minDate" @change="onDateChange()" required
                            class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    </div>
                </div>

                <template x-if="selectedCourt && selectedCourt.status === 'maintenance'">
                    <p class="mt-3 flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        <i class="ph ph-warning-circle"></i> This court is currently under maintenance.
                    </p>
                </template>
            </div>

            <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                        <i class="ph ph-clock text-sm"></i>
                        <span x-text="isPartial ? 'Pick where the affected hour(s) go' : 'Pick the new time'"></span>
                    </p>
                    <span class="flex items-center gap-1.5 text-[11px] font-medium text-ink-400 dark:text-ink-500">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                        Live
                    </span>
                </div>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Tap times to build one block — tapping a time that isn't next to your current picks starts a fresh selection there instead.</p>

                <template x-if="warning">
                    <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300" x-text="warning"></p>
                </template>

                <template x-if="loading">
                    <p class="mt-4 text-sm text-ink-500 dark:text-ink-400">Loading availability…</p>
                </template>

                <template x-if="!loading && error">
                    <p class="mt-4 text-sm text-ink-500 dark:text-ink-400" x-text="error"></p>
                </template>

                <template x-if="!loading && !error">
                    <div class="mt-4 space-y-4">
                        <template x-for="group in groupedSlots" :key="group.key">
                            <div>
                                <p class="flex items-center gap-1.5 text-xs font-semibold text-ink-400 uppercase dark:text-ink-500">
                                    <i class="ph" :class="group.icon"></i>
                                    <span x-text="group.label"></span>
                                </p>
                                <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                                    <template x-for="item in group.items" :key="item.slot.id">
                                        <button
                                            type="button"
                                            @click="pickSlot(item.index)"
                                            class="rounded-lg border px-2 py-2.5 text-xs font-semibold transition-all"
                                            :class="isSelected(item.index) ? 'border-accent-500 bg-accent-500 text-ink-950 shadow-sm scale-[1.02]' : 'border-sky-200 bg-sky-50 text-sky-800 hover:border-accent-400 hover:bg-accent-50 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200'"
                                            x-text="slotLabel(item.slot)"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-for="id in selectedSlotIds" :key="id">
                    <input type="hidden" name="court_slot_ids[]" :value="id">
                </template>
            </div>
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
                    <i class="ph ph-arrows-left-right text-sm"></i>
                    <span x-text="isPartial ? 'New time for affected hour(s)' : 'New session'"></span>
                </p>

                <p x-show="isPartial" x-cloak class="mt-2 text-xs text-ink-500 dark:text-ink-400">
                    <span x-text="affectedIds.length"></span> hour(s) moving; the rest of the original booking stays put.
                </p>

                <template x-if="selectedGroups.length > 0">
                    <div class="mt-3 space-y-1.5">
                        <template x-for="(group, i) in selectedGroups" :key="i">
                            <div class="rounded-xl bg-accent-50 px-3 py-2 dark:bg-accent-950">
                                <p class="font-display text-sm font-semibold text-ink-950 dark:text-white" x-text="dateLabelFor(group[0].slot_date)"></p>
                                <p class="flex items-center gap-1.5 text-xs text-ink-600 dark:text-ink-400">
                                    <i class="ph ph-clock text-xs"></i>
                                    <span x-text="formatTime(group[0].start_time)"></span>–<span x-text="formatTime(group[group.length - 1].end_time)"></span>
                                </p>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="selectedGroups.length === 0">
                    <p class="mt-2 text-sm text-ink-400">No new time picked yet.</p>
                </template>

                <div class="mt-3 flex items-center justify-between border-t border-ink-100 pt-3 dark:border-ink-800">
                    <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">New total</p>
                    <p class="font-display text-xl font-semibold text-ink-950 dark:text-white">₱<span x-text="totalPrice.toFixed(2)"></span></p>
                </div>

                <button
                    type="submit"
                    :disabled="selectedSlots.length === 0 || selectedGroups.length > 1"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-ink-950 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-ink-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400"
                >
                    <i class="ph ph-arrow-clockwise"></i>
                    <span x-text="isPartial ? 'Move affected hour(s)' : 'Confirm reschedule'"></span>
                </button>
                <p x-show="selectedGroups.length > 1" x-cloak class="mt-2 text-center text-xs text-amber-600 dark:text-amber-400">
                    Pick just one contiguous block — a session can only move to one place.
                </p>
                <p class="mt-2 text-center text-xs text-ink-400">No new payment is taken.</p>
            </div>
        </div>
    </form>

</x-layouts.admin>
