<section
    id="availability"
    class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8"
    x-data="availabilityGrid({ availabilityUrl: '{{ url('/api/availability') }}', isAuthenticated: {{ auth()->check() ? 'true' : 'false' }}, periodBoundaries: @js(\App\Models\OperatingHours::current()->periodBoundaries()) })"
>
    <div class="overflow-hidden rounded-3xl border border-ink-100 dark:border-ink-800">
        {{-- Header bar --}}
        <div class="flex items-center justify-between bg-ink-950 px-5 py-5 sm:px-8">
            <div>
                <p class="font-display text-lg font-semibold text-white sm:text-xl">Book a Court</p>
                <p class="mt-0.5 text-xs text-ink-300 sm:text-sm">Pick a date, then tap any number of time slots. They all go into one reservation.</p>
            </div>
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-500 text-ink-950">
                <i class="ph ph-calendar-check text-lg"></i>
            </span>
        </div>

        <div class="bg-white p-5 sm:p-8 dark:bg-ink-900">
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
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-xs transition-colors"
                                        :class="!cell ? 'invisible' : (
                                            cell.isSelected ? 'bg-accent-500 font-semibold text-white' :
                                            !cell.isAvailable ? 'text-ink-300 cursor-not-allowed dark:text-ink-700' :
                                            cell.isToday ? 'border border-accent-400 text-accent-700 hover:bg-accent-50 dark:text-accent-400' :
                                            'text-ink-700 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800'
                                        )"
                                        x-text="cell ? cell.day : ''"
                                    ></button>
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

                <div class="grid flex-1 grid-cols-7 gap-2">
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
                        </button>
                    </template>
                </div>

                <button
                    type="button"
                    @click="nextWeek()"
                    :disabled="windowStart >= dateStrip.length - 7"
                    class="flex h-11 w-9 shrink-0 items-center justify-center rounded-xl border border-ink-200 bg-white text-ink-500 transition-colors hover:border-accent-400 hover:text-accent-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-400"
                >
                    <i class="ph ph-caret-right text-base"></i>
                </button>
            </div>

            {{-- Step 2: Court and time --}}
            <div class="mt-6 flex items-center gap-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-500 text-xs font-bold text-white">2</span>
                <div>
                    <p class="text-[11px] font-semibold tracking-wide text-ink-400 uppercase dark:text-ink-500">Step 2</p>
                    <p class="text-sm font-semibold text-ink-950 dark:text-white">Choose court and time</p>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-ink-500 dark:text-ink-400">
                <span class="flex items-center gap-1.5"><i class="ph ph-check-circle text-sm text-sky-500"></i> Available</span>
                <span class="flex items-center gap-1.5"><i class="ph ph-clock text-sm text-amber-500"></i> Pending payment</span>
                <span class="flex items-center gap-1.5"><i class="ph ph-x-circle text-sm text-rose-500"></i> Booked</span>
            </div>

            <div class="mt-3 rounded-2xl border border-ink-100 dark:border-ink-800">
                <template x-if="loading">
                    <p class="p-6 text-sm text-ink-500 dark:text-ink-400">Loading availability…</p>
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

                        <div class="overflow-x-auto p-4">
                            <div class="min-w-140">
                                {{-- Period header row --}}
                                <div class="flex gap-3">
                                    <div class="w-28 shrink-0"></div>
                                    <div class="grid flex-1 gap-3" :style="`grid-template-columns: repeat(${periods.length}, minmax(0,1fr))`">
                                        <template x-for="period in periods" :key="'h-' + period.key">
                                            <p class="flex items-center gap-1.5 text-xs font-semibold text-ink-500 uppercase dark:text-ink-400">
                                                <i class="ph" :class="period.icon"></i>
                                                <span x-text="period.label"></span>
                                            </p>
                                        </template>
                                    </div>
                                </div>

                                {{-- Court row groups --}}
                                <template x-for="court in courts" :key="court.id">
                                    <div class="mt-4 flex gap-3 border-t border-ink-100 pt-4 first:mt-3 first:border-t-0 first:pt-0 dark:border-ink-800">
                                        <div class="flex w-28 shrink-0 items-center border-l-2 border-accent-400 pl-3">
                                            <p class="text-xs font-semibold tracking-wide text-ink-700 uppercase dark:text-ink-300" x-text="court.name"></p>
                                        </div>

                                        <div class="grid flex-1 gap-3" :style="`grid-template-columns: repeat(${periods.length}, minmax(0,1fr))`">
                                            <template x-for="period in periods" :key="court.id + '-' + period.key">
                                                <div class="space-y-2">
                                                    <template x-for="time in period.times" :key="court.id + '-' + time">
                                                        <button
                                                            type="button"
                                                            @click="pickCell(court, time)"
                                                            :disabled="!slotFor(court, time) || (slotFor(court, time).status !== 'available' && !isSelected(court, time))"
                                                            class="w-full rounded-lg border px-2 py-2 text-xs font-medium transition-colors"
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

            {{-- Selection summary bar: pinned to the viewport bottom so it stays visible while scrolling --}}
            <template x-if="selectedSlots.length > 0">
                <div class="fixed inset-x-0 bottom-0 z-40" @click.outside="showQuickDetails = false">
                    {{-- Expandable date/time details --}}
                    <div
                        x-show="showQuickDetails"
                        x-cloak
                        x-transition
                        class="mx-auto max-w-2xl rounded-t-3xl border border-b-0 border-accent-400 bg-accent-600 px-5 py-4"
                    >
                        <p class="text-xs font-semibold tracking-wide text-white/80 uppercase">Selected date &amp; time</p>
                        <p class="mt-1 text-sm font-semibold text-white" x-text="selectedDateLabel"></p>
                        <p class="mt-0.5 text-sm text-white/80">
                            <span x-text="selectedCourt.name"></span> &middot;
                            <span x-text="formatTime(selectedSlots[0].start_time)"></span> to
                            <span x-text="formatTime(selectedSlots[selectedSlots.length - 1].end_time)"></span>
                            (<span x-text="selectedSlots.length"></span> hour<span x-show="selectedSlots.length > 1">s</span>)
                        </p>
                    </div>

                    <div class="bg-accent-500 px-4 py-3 sm:px-6">
                        <div class="mx-auto flex max-w-2xl items-center justify-between gap-4">
                            <button type="button" @click="showQuickDetails = !showQuickDetails" class="flex flex-1 items-center gap-3 text-left">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15 text-sm font-bold text-white">1</span>
                                <span>
                                    <span class="block font-display text-lg font-semibold text-white">₱<span x-text="totalPrice.toFixed(2)"></span></span>
                                    <span class="block text-xs text-white/80">1 booking &middot; <span x-text="selectedSlots.length"></span> slot<span x-show="selectedSlots.length > 1">s</span>, 1 court</span>
                                </span>
                                <i class="ph text-lg text-white" :class="showQuickDetails ? 'ph-caret-down' : 'ph-caret-up'"></i>
                            </button>

                            <button
                                type="button"
                                @click="showReserveSheet = true"
                                class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-ink-950 px-5 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-ink-800"
                            >
                                <i class="ph ph-calendar-check"></i>
                                Reserve
                            </button>
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
                                <p class="text-sm text-ink-300">1 booking</p>
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
                                    <p class="text-sm font-semibold text-white">1 booking</p>
                                </div>
                                <div class="flex items-start justify-between gap-3 bg-accent-50 px-4 py-3 dark:bg-accent-950">
                                    <div>
                                        <p class="text-sm font-semibold text-ink-950 dark:text-white">
                                            <span x-text="selectedCourt.name"></span> &middot; <span x-text="selectedSlots.length"></span>h
                                        </p>
                                        <p class="text-xs text-ink-500 dark:text-ink-400">
                                            <span x-text="selectedDateLabel"></span> &middot;
                                            <span x-text="formatTime(selectedSlots[0].start_time)"></span> to
                                            <span x-text="formatTime(selectedSlots[selectedSlots.length - 1].end_time)"></span>
                                        </p>
                                    </div>
                                    <p class="shrink-0 font-mono text-sm font-semibold text-ink-950 dark:text-white">₱<span x-text="totalPrice.toFixed(2)"></span></p>
                                </div>
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
