<x-layouts.app :title="$court->name.' — book a slot'" :hide-footer="true">

    <section
        class="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8"
        :class="selectedSlots.length > 0 ? 'pb-24' : ''"
        x-data="bookingCalendar({ courtId: {{ $court->id }}, slotsUrl: '{{ url('/api/courts/'.$court->id.'/slots') }}', fullyBookedUrl: '{{ url('/api/courts/'.$court->id.'/fully-booked-dates') }}', periodBoundaries: @js($periodBoundaries), periodEnds: @js($periodEnds), maxBookingHours: {{ $maxBookingHours }} })"
    >
        <a href="{{ route('book.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left"></i>
            All courts
        </a>

        <h1 class="mt-4 font-display text-3xl font-semibold tracking-tight text-ink-950 md:text-4xl dark:text-white">
            {{ $court->name }}
        </h1>
        @if ($court->location)
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $court->location }}</p>
        @endif

        <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-5">
            {{-- Calendar --}}
            <div class="lg:col-span-3">
                <div class="rounded-2xl border border-ink-100 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                    <div class="flex items-center justify-between">
                        <button type="button" @click="prevMonth()" class="cursor-pointer rounded-full p-2 text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" aria-label="Previous month">
                            <i class="ph ph-caret-left text-lg"></i>
                        </button>
                        <p class="font-display text-sm font-semibold text-ink-950 dark:text-white" x-text="monthLabel"></p>
                        <button type="button" @click="nextMonth()" class="cursor-pointer rounded-full p-2 text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" aria-label="Next month">
                            <i class="ph ph-caret-right text-lg"></i>
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-7 gap-1 text-center text-xs font-medium text-ink-400 dark:text-ink-500">
                        <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                    </div>

                    <div class="mt-1 grid grid-cols-7 gap-1">
                        <template x-for="(cell, i) in calendarDays" :key="i">
                            <button
                                type="button"
                                x-show="cell"
                                :disabled="cell && cell.isPast"
                                :title="cell && cell.isFullyBooked ? 'Fully booked' : ''"
                                @click="cell && selectDate(cell.dateStr, cell.isPast)"
                                class="relative aspect-square cursor-pointer rounded-xl text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:text-ink-300 dark:disabled:text-ink-700"
                                :class="cell && cell.isFullyBooked
                                    ? (selectedDateStr === cell.dateStr ? 'bg-rose-600 text-white' : 'bg-rose-100 font-semibold text-rose-700 hover:bg-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:hover:bg-rose-900')
                                    : (cell && selectedDateStr === cell.dateStr
                                        ? 'bg-accent-500 text-ink-950'
                                        : (cell && cell.isToday ? 'border border-accent-400 text-ink-900 dark:text-white' : 'text-ink-700 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800'))"
                            >
                                <span x-text="cell ? cell.day : ''"></span>
                                <span
                                    x-show="cell && cell.hasPick && selectedDateStr !== cell.dateStr"
                                    x-cloak
                                    class="absolute bottom-1 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rounded-full bg-accent-500"
                                ></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Slot picker --}}
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-ink-100 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                    <template x-if="!selectedDateStr">
                        <p class="text-sm text-ink-500 dark:text-ink-400">Pick a date to see open times.</p>
                    </template>

                    <template x-if="selectedDateStr && loading">
                        <p class="text-sm text-ink-500 dark:text-ink-400">Loading availability…</p>
                    </template>

                    <template x-if="selectedDateStr && !loading && error">
                        <p class="text-sm text-rose-600 dark:text-rose-400" x-text="error"></p>
                    </template>

                    <template x-if="warning">
                        <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300" x-text="warning"></p>
                    </template>

                    <template x-if="selectedDateStr && !loading && slots.length > 0">
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-medium text-ink-800 dark:text-ink-200">
                                    Tap any times you'd like to book — non-contiguous picks become separate bookings under one payment.
                                </p>
                                <span class="flex shrink-0 items-center gap-1.5 text-[11px] font-medium text-ink-400 dark:text-ink-500">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                    Live
                                </span>
                            </div>
                            <div class="mt-3 space-y-4">
                                <template x-for="group in groupedSlots" :key="group.label">
                                    <div>
                                        <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide" :class="group.color">
                                            <i class="ph text-sm" :class="group.icon"></i>
                                            <span x-text="group.label"></span>
                                        </p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <template x-for="item in group.items" :key="item.slot.id">
                                                <button
                                                    type="button"
                                                    @click="pickSlot(item.index)"
                                                    :disabled="item.slot.status !== 'available'"
                                                    :title="item.slot.status === 'booked' ? 'Already booked' : (item.slot.status === 'pending' ? 'Payment pending' : (item.slot.status === 'blocked' ? 'Blocked' : ''))"
                                                    class="rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
                                                    :class="item.slot.status !== 'available'
                                                        ? 'cursor-not-allowed border-rose-200 bg-rose-50 text-rose-500 line-through dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-400'
                                                        : (isSelected(item.index)
                                                            ? 'cursor-pointer border-accent-500 bg-accent-500 text-white'
                                                            : 'cursor-pointer border-ink-200 text-ink-700 hover:border-accent-400 dark:border-ink-700 dark:text-ink-300')"
                                                    x-text="slotLabel(item.slot)"
                                                ></button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                        </div>
                    </template>
                </div>

                @error('court_slot_ids')
                    <p class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Selection summary: floating cart-style card pinned above the viewport bottom --}}
        <template x-if="selectedSlots.length > 0">
            <div class="fixed inset-x-0 bottom-0 z-40 p-4">
                <div class="mx-auto max-w-md rounded-3xl bg-accent-500 p-4 shadow-[0_12px_40px_rgba(0,0,0,0.45)] ring-1 ring-black/10">
                    <div class="flex items-center justify-between gap-3">
                        <button type="button" @click="showQuickDetails = !showQuickDetails" class="flex items-center gap-1.5 text-sm font-medium text-white">
                            <i class="ph ph-shopping-cart-simple text-base"></i>
                            <span x-text="selectedSlots.length"></span> slot<span x-show="selectedSlots.length > 1">s</span>
                            <i class="ph ph-caret-down text-xs text-white/70 transition-transform" :class="showQuickDetails && 'rotate-180'"></i>
                        </button>

                        <div class="flex items-center gap-3">
                            <span class="font-display text-lg font-semibold text-white">₱<span x-text="totalPrice.toFixed(2)"></span></span>
                            <button
                                type="button"
                                @click="showReserveSheet = true"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-ink-950 px-4 py-2 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-ink-800"
                            >
                                Book Now
                                <i class="ph ph-arrow-right text-base"></i>
                            </button>
                        </div>
                    </div>

                    <div x-show="showQuickDetails" x-cloak x-transition class="mt-3 rounded-2xl bg-black/10 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="min-w-0 truncate text-sm text-white">
                                <span class="font-semibold">{{ $court->name }}</span>
                                <span class="text-white/70">&middot;</span>
                                <span x-text="selectedGroups.length"></span> booking<span x-show="selectedGroups.length > 1">s</span>
                            </p>
                            <button type="button" @click="clearSelection()" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white/70 transition-colors hover:bg-white/10 hover:text-white" aria-label="Clear selection">
                                <i class="ph ph-x text-sm"></i>
                            </button>
                        </div>
                        <div class="mt-1.5 space-y-0.5">
                            <template x-for="(group, i) in selectedGroups" :key="i">
                                <p class="text-xs text-white/80" x-text="dateLabelFor(group[0].slot_date) + ', ' + formatTime(group[0].start_time) + '–' + formatTime(group[group.length - 1].end_time)"></p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- "Complete Your Booking" modal --}}
        <template x-if="selectedSlots.length > 0">
            <div
                x-show="showReserveSheet"
                x-cloak
                x-transition.opacity
                @keydown.escape.window="showReserveSheet = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/70 p-4"
            >
                <div
                    @click.outside="showReserveSheet = false"
                    x-transition
                    class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-ink-900"
                >
                    <div class="flex items-start justify-between rounded-t-3xl bg-ink-950 p-5">
                        <div>
                            <p class="font-display text-lg font-semibold text-white">Complete Your Booking</p>
                            <p class="text-sm text-ink-300"><span x-text="selectedGroups.length"></span> booking<span x-show="selectedGroups.length > 1">s</span> &middot; {{ $court->name }}</p>
                        </div>
                        <button type="button" @click="showReserveSheet = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Close">
                            <i class="ph ph-x text-lg"></i>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('book.store', $court) }}" class="p-5">
                        @csrf
                        <template x-for="id in selectedSlotIds" :key="id">
                            <input type="hidden" name="court_slot_ids[]" :value="id">
                        </template>

                        <div class="overflow-hidden rounded-2xl border border-ink-100 dark:border-ink-800">
                            <template x-for="(group, i) in selectedGroups" :key="i">
                                <div class="flex items-start justify-between gap-3 bg-accent-50 px-4 py-3 dark:bg-accent-950" :class="i > 0 && 'border-t border-accent-100 dark:border-accent-900'">
                                    <div>
                                        <p class="text-sm font-semibold text-ink-950 dark:text-white">
                                            {{ $court->name }} &middot; <span x-text="group.length"></span>h
                                        </p>
                                        <p class="text-xs text-ink-500 dark:text-ink-400">
                                            <span x-text="dateLabelFor(group[0].slot_date)"></span> &middot;
                                            <span x-text="formatTime(group[0].start_time)"></span> to
                                            <span x-text="formatTime(group[group.length - 1].end_time)"></span>
                                        </p>
                                    </div>
                                    <p class="shrink-0 font-mono text-sm font-semibold text-ink-950 dark:text-white">₱<span x-text="group.reduce((sum, s) => sum + parseFloat(s.price), 0).toFixed(2)"></span></p>
                                </div>
                            </template>
                            <div class="flex items-center justify-between border-t border-ink-100 bg-white px-4 py-3 dark:border-ink-800 dark:bg-ink-900">
                                <p class="text-xs font-semibold tracking-wide text-accent-700 uppercase dark:text-accent-400">Total</p>
                                <p class="font-display text-lg font-semibold text-ink-950 dark:text-white">₱<span x-text="totalPrice.toFixed(2)"></span></p>
                            </div>
                        </div>

                        <template x-if="selectedGroups.length > 1">
                            <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">These will be booked as <span x-text="selectedGroups.length"></span> separate bookings, covered by one payment.</p>
                        </template>

                        @guest
                            <div class="mt-5 rounded-xl border border-accent-200 bg-accent-50 px-4 py-3 dark:border-accent-800 dark:bg-accent-950">
                                <p class="text-sm font-semibold text-ink-950 dark:text-white">You'll need an account to book</p>
                                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">
                                    <a href="{{ route('login') }}" class="font-medium text-accent-700 underline hover:text-accent-600 dark:text-accent-400">Log in</a>
                                    or
                                    <a href="{{ route('register') }}" class="font-medium text-accent-700 underline hover:text-accent-600 dark:text-accent-400">create an account</a>
                                    to complete this booking. Your selection will be kept.
                                </p>
                            </div>
                        @endguest

                        <button
                            type="submit"
                            class="mt-5 flex w-full items-center justify-center gap-2 rounded-full bg-accent-500 px-6 py-3 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400"
                        >
                            <i class="ph ph-calendar-check text-base"></i>
                            Continue to payment
                        </button>

                        <button type="button" @click="clearSelection()" class="mt-2 w-full text-center text-xs font-medium text-ink-500 hover:text-ink-700 dark:text-ink-400">
                            Clear selection
                        </button>
                    </form>
                </div>
            </div>
        </template>
    </section>

</x-layouts.app>
