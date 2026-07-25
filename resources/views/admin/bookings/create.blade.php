<x-layouts.admin :title="'New Booking'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">New Booking</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Book a court on a walk-in customer's behalf. This confirms immediately — no payment step.</p>
        </div>
        <a href="{{ route('admin.bookings.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">Back to bookings</a>
    </div>

    @error('court_slot_ids')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            {{ $message }}
        </div>
    @enderror

    @if ($courts->isEmpty())
        <div class="mt-6 rounded-2xl border border-dashed border-ink-200 p-6 text-center text-sm text-ink-500 dark:border-ink-800 dark:text-ink-400">
            No active courts available to book. Add or activate a court first.
        </div>
    @else
        <form
            method="POST"
            action="{{ route('admin.bookings.store') }}"
            class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_360px]"
            x-data="adminBookingForm({
                courts: @js($courts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'status' => $c->status])),
                slotsUrlBase: '{{ url('/api/courts') }}',
                periodBoundaries: @js($periodBoundaries),
                periodEnds: @js($periodEnds),
                initialCourtId: @js(isset($prefill['court_id']) ? (int) $prefill['court_id'] : null),
            })"
        >
            @csrf

            <div class="space-y-4">
                <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Court</label>
                        <select name="court_id" x-model="courtId" @change="onCourtChange()" required
                            class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                            @foreach ($courts as $court)
                                <option value="{{ $court->id }}">{{ $court->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <template x-if="selectedCourt && selectedCourt.status === 'maintenance'">
                        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">This court is currently under maintenance.</p>
                    </template>

                    {{-- Date strip, same style as the home page booking widget --}}
                    <div class="mt-4 flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Date</label>
                        <div class="flex items-center gap-1.5">
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
                                        <p class="text-xs font-semibold text-ink-800 dark:text-ink-200" x-text="calendarLabel()"></p>
                                        <button type="button" @click="nextCalendarMonth()" class="flex h-7 w-7 items-center justify-center rounded-lg text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800">
                                            <i class="ph ph-caret-right text-sm"></i>
                                        </button>
                                    </div>

                                    <div class="mt-2 grid grid-cols-7 gap-1 text-center text-[10px] font-medium text-ink-400 dark:text-ink-500">
                                        <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                                    </div>

                                    <template x-for="(week, wi) in calendarWeeks()" :key="wi">
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

                            <button type="button" @click="prevDateWindow()" :disabled="dateWindowStart === 0"
                                class="flex h-11 w-9 shrink-0 items-center justify-center rounded-xl border border-ink-200 text-ink-500 hover:border-accent-400 hover:text-accent-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-ink-700 dark:text-ink-400">
                                <i class="ph ph-caret-left text-base"></i>
                            </button>

                            <div class="grid flex-1 grid-cols-4 gap-1.5">
                                <template x-for="d in visibleDates()" :key="d.dateStr">
                                    <button
                                        type="button"
                                        @click="selectDate(d.dateStr)"
                                        class="relative flex flex-col items-center rounded-xl border px-1 py-2.5 transition-colors"
                                        :class="dateStr === d.dateStr
                                            ? 'border-accent-500 bg-accent-500 text-white'
                                            : (d.isToday
                                                ? 'border-accent-300 bg-white text-ink-700 hover:border-accent-500 dark:border-accent-800 dark:bg-ink-950 dark:text-ink-300'
                                                : 'border-ink-100 bg-white text-ink-700 hover:border-accent-400 dark:border-ink-800 dark:bg-ink-950 dark:text-ink-300')"
                                    >
                                        <span x-show="d.isToday" class="absolute -top-2 rounded-full bg-accent-500 px-1.5 py-0.5 text-[9px] font-bold text-white">Today</span>
                                        <span class="text-[10px] font-medium uppercase" x-text="d.weekday"></span>
                                        <span class="font-display text-base font-semibold" x-text="d.day"></span>
                                        <span class="text-[10px]" x-text="d.month"></span>
                                    </button>
                                </template>
                            </div>

                            <button type="button" @click="nextDateWindow()" :disabled="dateWindowStart >= dateStrip.length - dateWindowSize"
                                class="flex h-11 w-9 shrink-0 items-center justify-center rounded-xl border border-ink-200 text-ink-500 hover:border-accent-400 hover:text-accent-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-ink-700 dark:text-ink-400">
                                <i class="ph ph-caret-right text-base"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-ink-950 dark:text-white">Pick time slots</p>
                        <span class="flex items-center gap-1.5 text-[11px] font-medium text-ink-400 dark:text-ink-500">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                            Live
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Tap any slots you'd like to book. Non-contiguous picks become separate confirmed bookings. This list refreshes automatically, so a slot someone else just booked won't stay pickable.</p>

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
                                                class="rounded-lg border px-2 py-2 text-xs font-medium transition-colors"
                                                :class="isSelected(item.index) ? 'border-accent-500 bg-accent-500 text-ink-950' : 'border-sky-200 bg-sky-50 text-sky-800 hover:border-accent-400 hover:bg-accent-50 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200'"
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

            <div class="space-y-4">
                <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                    <p class="text-sm font-semibold text-ink-950 dark:text-white">Customer details</p>

                    <div class="mt-3 flex flex-col gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Full name</label>
                            <input name="guest_name" type="text" required placeholder="Juan Dela Cruz" value="{{ old('guest_name', $prefill['guest_name'] ?? '') }}"
                                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                            @error('guest_name')
                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Phone</label>
                            <input name="guest_phone" type="tel" pattern="^(09\d{9}|\+639\d{9})$" title="Enter a valid PH mobile number, e.g. 09171234567 or +639171234567" placeholder="09XX-XXX-XXXX" value="{{ old('guest_phone', $prefill['guest_phone'] ?? '') }}"
                                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                            @error('guest_phone')
                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Email</label>
                            <input name="guest_email" type="email" placeholder="you@email.com" value="{{ old('guest_email', $prefill['guest_email'] ?? '') }}"
                                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                            @error('guest_email')
                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                    <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Total</p>
                    <p class="mt-1 font-display text-2xl font-semibold text-ink-950 dark:text-white">₱<span x-text="totalPrice.toFixed(2)"></span></p>
                    <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">
                        <span x-text="selectedSlots.length"></span> slot<span x-show="selectedSlots.length !== 1">s</span> selected
                        <template x-if="selectedGroups.length > 1">
                            <span>&middot; <span x-text="selectedGroups.length"></span> separate bookings</span>
                        </template>
                    </p>

                    <template x-if="selectedGroups.length > 0">
                        <ul class="mt-3 space-y-1 border-t border-ink-100 pt-3 dark:border-ink-800">
                            <template x-for="(group, i) in selectedGroups" :key="i">
                                <li class="text-xs text-ink-600 dark:text-ink-400">
                                    <span x-text="dateLabelFor(group[0].slot_date)"></span>,
                                    <span x-text="formatTime(group[0].start_time)"></span>–<span x-text="formatTime(group[group.length - 1].end_time)"></span>
                                </li>
                            </template>
                        </ul>
                    </template>

                    <button
                        type="submit"
                        :disabled="selectedSlots.length === 0"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-ink-950 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-ink-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400"
                    >
                        <i class="ph ph-calendar-check"></i>
                        <span x-text="selectedGroups.length > 1 ? 'Confirm ' + selectedGroups.length + ' bookings' : 'Confirm booking'"></span>
                    </button>
                    <p class="mt-2 text-center text-xs text-ink-400">Skips payment — confirmed right away.</p>
                </div>
            </div>
        </form>
    @endif

</x-layouts.admin>
