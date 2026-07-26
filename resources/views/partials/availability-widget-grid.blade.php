@php $__operatingHours = \App\Models\OperatingHours::current(); @endphp

<section
    id="availability"
    class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
    x-data="availabilityGrid({ availabilityUrl: '{{ url('/api/availability') }}', isAuthenticated: {{ auth()->check() ? 'true' : 'false' }}, periodBoundaries: @js($__operatingHours->periodBoundaries()), periodEnds: @js($__operatingHours->periodEnds()), maxBookingHours: {{ $__operatingHours->max_customer_booking_hours ?? 24 }} })"
>
    <div class="overflow-hidden rounded-3xl border border-ink-100 dark:border-ink-800">
        {{-- Header bar --}}
        <div class="flex items-center justify-between bg-ink-950 px-4 py-4 sm:px-8 sm:py-5">
            <div>
                <p class="font-display text-base font-semibold text-white sm:text-xl">Book a Court</p>
                <p class="mt-0.5 text-xs text-ink-300 sm:text-sm">Pick a date, then tap any number of time slots. Non-contiguous picks become separate bookings under one payment.</p>
            </div>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-accent-500 text-ink-950 sm:h-10 sm:w-10">
                <i class="ph ph-calendar-check text-base sm:text-lg"></i>
            </span>
        </div>

        <div class="bg-white p-4 sm:p-8 dark:bg-ink-900">
            {{-- Step 1: Date --}}
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-500 text-xs font-bold text-white">1</span>
                <div>
                    <p class="text-[11px] font-semibold tracking-wide text-ink-400 uppercase dark:text-ink-500">Step 1</p>
                    <p class="text-sm font-semibold text-ink-950 dark:text-white">Choose date</p>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 rounded-2xl border border-ink-100 bg-ink-50/60 p-3 dark:border-ink-800 dark:bg-ink-950/40">
                <div class="relative shrink-0" @click.outside="showCalendar = false">
                    <button
                        type="button"
                        @click="showCalendar ? (showCalendar = false) : openCalendar()"
                        class="flex h-11 w-11 items-center justify-center rounded-xl border transition-colors"
                        :class="showCalendar ? 'border-accent-500 bg-accent-50 text-accent-700 dark:border-accent-700 dark:bg-accent-950' : 'border-ink-200 bg-white text-ink-500 hover:border-accent-400 hover:text-accent-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-400'"
                        title="Pick a date"
                    >
                        <i class="ph ph-calendar-blank text-lg"></i>
                    </button>

                    <div
                        x-show="showCalendar"
                        x-cloak
                        x-transition
                        class="absolute top-full left-0 z-20 mt-2 w-64 rounded-2xl border border-ink-100 bg-white p-3 shadow-xl dark:border-ink-800 dark:bg-ink-900"
                    >
                        <div class="flex items-center justify-between">
                            <button type="button" @click="prevCalendarMonth()" class="flex h-7 w-7 items-center justify-center rounded-lg text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800">
                                <i class="ph ph-caret-left text-sm"></i>
                            </button>
                            <p class="text-xs font-semibold text-ink-800 dark:text-ink-200" x-text="calendarLabel"></p>
                            <button type="button" @click="nextCalendarMonth()" class="flex h-7 w-7 items-center justify-center rounded-lg text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800">
                                <i class="ph ph-caret-right text-sm"></i>
                            </button>
                        </div>

                        <div class="mt-2 grid grid-cols-7 gap-1 text-center text-[10px] font-medium text-ink-400 dark:text-ink-500">
                            <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                        </div>

                        <template x-for="(week, wi) in calendarWeeks" :key="wi">
                            <div class="grid grid-cols-7 gap-1">
                                <template x-for="(cell, ci) in week" :key="ci">
                                    <button
                                        type="button"
                                        @click="pickCalendarDate(cell)"
                                        :disabled="!cell || !cell.isAvailable"
                                        class="relative flex h-8 w-8 items-center justify-center rounded-lg text-xs transition-colors"
                                        :class="!cell ? 'invisible' : (
                                            cell.isSelected ? 'bg-accent-500 font-semibold text-white' :
                                            !cell.isAvailable ? 'text-ink-300 cursor-not-allowed dark:text-ink-700' :
                                            cell.isToday ? 'border border-accent-400 text-accent-700 hover:bg-accent-50 dark:text-accent-400' :
                                            'text-ink-700 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800'
                                        )"
                                    >
                                        <span x-text="cell ? cell.day : ''"></span>
                                        <span
                                            x-show="cell && cell.isAvailable && hasPickOn(cell.dateStr) && !cell.isSelected"
                                            x-cloak
                                            class="absolute bottom-0.5 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-accent-500"
                                        ></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <button
                    type="button"
                    @click="prevWeek()"
                    :disabled="windowStart === 0"
                    class="flex h-11 w-9 shrink-0 items-center justify-center rounded-xl border border-ink-200 bg-white text-ink-500 transition-colors hover:border-accent-400 hover:text-accent-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-400"
                >
                    <i class="ph ph-caret-left text-base"></i>
                </button>

                <div class="grid flex-1 grid-cols-3 gap-2 sm:grid-cols-7">
                    <template x-for="d in visibleDates" :key="d.dateStr">
                        <button
                            type="button"
                            @click="selectDate(d.dateStr)"
                            class="relative flex flex-col items-center rounded-xl border px-1 py-2.5 transition-colors"
                            :class="selectedDateStr === d.dateStr
                                ? 'border-accent-500 bg-accent-500 text-white'
                                : (d.isToday
                                    ? 'border-accent-300 bg-white text-ink-700 hover:border-accent-500 dark:border-accent-800 dark:bg-ink-900 dark:text-ink-300'
                                    : 'border-ink-100 bg-white text-ink-700 hover:border-accent-400 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-300')"
                        >
                            <span
                                x-show="d.isToday"
                                class="absolute -top-2 rounded-full bg-accent-500 px-1.5 py-0.5 text-[9px] font-bold text-white"
                            >Today</span>
                            <span class="text-[10px] font-medium uppercase" x-text="d.weekday"></span>
                            <span class="font-display text-base font-semibold sm:text-lg" x-text="d.day"></span>
                            <span class="text-[10px]" x-text="d.month"></span>
                            <span
                                x-show="hasPickOn(d.dateStr) && selectedDateStr !== d.dateStr"
                                x-cloak
                                class="absolute bottom-1 h-1.5 w-1.5 rounded-full bg-accent-500"
                            ></span>
                        </button>
                    </template>
                </div>

                <button
                    type="button"
                    @click="nextWeek()"
                    :disabled="windowStart >= dateStrip.length - windowSize"
                    class="flex h-11 w-9 shrink-0 items-center justify-center rounded-xl border border-ink-200 bg-white text-ink-500 transition-colors hover:border-accent-400 hover:text-accent-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-400"
                >
                    <i class="ph ph-caret-right text-base"></i>
                </button>
            </div>

            {{-- Step 2: Court and time --}}
            <div class="mt-6 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-500 text-xs font-bold text-white">2</span>
                    <div>
                        <p class="text-[11px] font-semibold tracking-wide text-ink-400 uppercase dark:text-ink-500">Step 2</p>
                        <p class="text-sm font-semibold text-ink-950 dark:text-white">Choose court and time</p>
                    </div>
                </div>
                <span class="flex items-center gap-1.5 text-[11px] font-medium text-ink-400 dark:text-ink-500">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                    Live
                </span>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-4 text-sm font-bold text-ink-600 dark:text-ink-300">
                <span class="flex items-center gap-1.5"><i class="ph ph-check-circle text-lg text-sky-500"></i> Available</span>
                <span class="flex items-center gap-1.5"><i class="ph ph-clock text-lg text-amber-500"></i> Pending payment</span>
                <span class="flex items-center gap-1.5"><i class="ph ph-x-circle text-lg text-rose-500"></i> Booked</span>
            </div>

            <template x-if="warning">
                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300" x-text="warning"></p>
            </template>

            <div class="mt-3 min-h-90 rounded-2xl border border-ink-100 dark:border-ink-800">
                <template x-if="loading">
                    <div class="flex min-h-90 flex-col items-center justify-center gap-3">
                        @if ($__operatingHours->logoUrl())
                            <img src="{{ $__operatingHours->logoUrl() }}" alt="" class="h-12 w-12 animate-zoom-pulse">
                        @else
                            <x-logo-mark class="h-12 w-12 animate-zoom-pulse" />
                        @endif
                        <p class="text-sm text-ink-500 dark:text-ink-400">Loading availability…</p>
                    </div>
                </template>

                <template x-if="!loading && error">
                    <p class="p-6 text-sm text-ink-500 dark:text-ink-400" x-text="error"></p>
                </template>

                <template x-if="!loading && !error && courts.length > 0">
                    <div>
                        <div class="border-b border-ink-100 bg-ink-100/50 px-4 py-2 text-center text-xs font-semibold text-ink-600 dark:border-ink-800 dark:bg-ink-800/50 dark:text-ink-300">
                            <i class="ph ph-calendar mr-1 align-[-2px]"></i>
                            <span x-text="selectedDateLabel"></span>
                        </div>

                        <div class="p-3 sm:p-4">
                            <div class="sm:min-w-140">
                                {{-- Court header row --}}
                                <div class="grid gap-1.5 sm:gap-3" :style="`grid-template-columns: repeat(${courts.length}, minmax(0,1fr))`">
                                    <template x-for="court in courts" :key="'h-' + court.id">
                                        <p class="truncate text-center text-[10px] font-semibold tracking-wide text-ink-500 uppercase sm:text-xs dark:text-ink-400" x-text="court.name"></p>
                                    </template>
                                </div>

                                {{-- Period groups --}}
                                <template x-for="period in periods" :key="period.key">
                                    <div class="mt-4">
                                        <p class="flex items-center gap-1.5 text-sm font-bold text-ink-700 uppercase sm:text-lg sm:font-extrabold dark:text-ink-200">
                                            <i class="ph" :class="period.icon"></i>
                                            <span x-text="period.label"></span>
                                        </p>
                                        <div class="mt-2 space-y-1.5 sm:space-y-2">
                                            <template x-for="time in period.times" :key="time">
                                                <div class="grid gap-1.5 sm:gap-3" :style="`grid-template-columns: repeat(${courts.length}, minmax(0,1fr))`">
                                                    <template x-for="court in courts" :key="court.id + '-' + time">
                                                        <button
                                                            type="button"
                                                            @click="pickCell(court, time)"
                                                            :disabled="!slotFor(court, time) || (slotFor(court, time).status !== 'available' && !isSelected(court, time))"
                                                            class="rounded-lg border px-1.5 py-2 text-sm font-bold transition-colors sm:px-2 sm:py-2.5 sm:text-base"
                                                            :class="cellClass(court, time)"
                                                            x-text="slotFor(court, time) ? cellLabel(time, court) : ''"
                                                        ></button>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
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
                                    <span class="font-semibold" x-text="selectedCourt.name"></span>
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
                                <p class="text-sm text-ink-300"><span x-text="selectedGroups.length"></span> booking<span x-show="selectedGroups.length > 1">s</span></p>
                            </div>
                            <button type="button" @click="showReserveSheet = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Close">
                                <i class="ph ph-x text-lg"></i>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('quick-book') }}" class="p-5">
                            @csrf
                            <input type="hidden" name="court_id" :value="selectedCourtId">
                            <template x-for="id in selectedSlotIds" :key="id">
                                <input type="hidden" name="court_slot_ids[]" :value="id">
                            </template>

                            <div class="overflow-hidden rounded-2xl border border-ink-100 dark:border-ink-800">
                                <div class="bg-accent-500 px-4 py-3">
                                    <p class="text-[10px] font-semibold tracking-wide text-white/80 uppercase">Your reservation</p>
                                    <p class="text-sm font-semibold text-white"><span x-text="selectedGroups.length"></span> booking<span x-show="selectedGroups.length > 1">s</span></p>
                                </div>
                                <template x-for="(group, i) in selectedGroups" :key="i">
                                    <div class="flex items-start justify-between gap-3 bg-accent-50 px-4 py-3 dark:bg-accent-950" :class="i > 0 && 'border-t border-accent-100 dark:border-accent-900'">
                                        <div>
                                            <p class="text-sm font-semibold text-ink-950 dark:text-white">
                                                <span x-text="selectedCourt.name"></span> &middot; <span x-text="group.length"></span>h
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

                            <template x-if="!isAuthenticated">
                                <div class="mt-5">
                                    <p class="text-sm font-semibold text-ink-950 dark:text-white">Your details</p>

                                    <div class="mt-3 flex flex-col gap-3">
                                        <div class="flex flex-col gap-1.5">
                                            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Full name</label>
                                            <input
                                                name="guest_name"
                                                type="text"
                                                required
                                                placeholder="Juan Dela Cruz"
                                                value="{{ old('guest_name') }}"
                                                class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white"
                                            >
                                        </div>

                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div class="flex flex-col gap-1.5">
                                                <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Phone</label>
                                                <input
                                                    name="guest_phone"
                                                    type="tel"
                                                    required
                                                    pattern="^(09\d{9}|\+639\d{9})$"
                                                    title="Enter a valid PH mobile number, e.g. 09171234567 or +639171234567"
                                                    placeholder="09XX-XXX-XXXX"
                                                    value="{{ old('guest_phone') }}"
                                                    class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white"
                                                >
                                            </div>
                                            <div class="flex flex-col gap-1.5">
                                                <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Email</label>
                                                <input
                                                    name="guest_email"
                                                    type="email"
                                                    required
                                                    placeholder="you@email.com"
                                                    value="{{ old('guest_email') }}"
                                                    class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <button
                                type="submit"
                                class="mt-5 flex w-full items-center justify-center gap-2 rounded-full bg-accent-500 px-6 py-3 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400"
                            >
                                <i class="ph ph-calendar-check text-base"></i>
                                Reserve Court
                            </button>

                            <button type="button" @click="clearSelection()" class="mt-2 w-full text-center text-xs font-medium text-ink-500 hover:text-ink-700 dark:text-ink-400">
                                Clear selection
                            </button>

                            <template x-if="!isAuthenticated">
                                <p class="mt-3 text-center text-xs text-ink-400">No account needed. You'll receive a reference number to track your booking.</p>
                            </template>
                        </form>
                    </div>
                </div>
            </template>

            @error('court_slot_ids')
                <p class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>
