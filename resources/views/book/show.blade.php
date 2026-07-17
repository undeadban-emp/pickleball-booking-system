<x-layouts.app :title="$court->name.' — book a slot'">

    <section
        class="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8"
        x-data="bookingCalendar({ courtId: {{ $court->id }}, slotsUrl: '{{ url('/api/courts/'.$court->id.'/slots') }}' })"
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
                        <button type="button" @click="prevMonth()" class="rounded-full p-2 text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" aria-label="Previous month">
                            <i class="ph ph-caret-left text-lg"></i>
                        </button>
                        <p class="font-display text-sm font-semibold text-ink-950 dark:text-white" x-text="monthLabel"></p>
                        <button type="button" @click="nextMonth()" class="rounded-full p-2 text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" aria-label="Next month">
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
                                @click="cell && selectDate(cell.dateStr, cell.isPast)"
                                class="aspect-square rounded-xl text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:text-ink-300 dark:disabled:text-ink-700"
                                :class="cell && selectedDateStr === cell.dateStr
                                    ? 'bg-accent-500 text-ink-950'
                                    : (cell && cell.isToday ? 'border border-accent-400 text-ink-900 dark:text-white' : 'text-ink-700 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800')"
                                x-text="cell ? cell.day : ''"
                            ></button>
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

                    <template x-if="selectedDateStr && !loading && slots.length > 0">
                        <div>
                            <p class="text-sm font-medium text-ink-800 dark:text-ink-200">
                                Tap a start time, then an end time to select a block.
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <template x-for="(slot, index) in slots" :key="slot.id">
                                    <button
                                        type="button"
                                        @click="pickSlot(index)"
                                        class="rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
                                        :class="isSelected(index)
                                            ? 'border-accent-500 bg-accent-500 text-ink-950'
                                            : 'border-ink-200 text-ink-700 hover:border-accent-400 dark:border-ink-700 dark:text-ink-300'"
                                        x-text="formatTime(slot.start_time)"
                                    ></button>
                                </template>
                            </div>

                            <template x-if="selectedSlots.length > 0">
                                <div class="mt-5 border-t border-ink-100 pt-4 dark:border-ink-800">
                                    <p class="text-sm text-ink-600 dark:text-ink-400">
                                        <span x-text="selectedSlots.length"></span> hour<span x-show="selectedSlots.length > 1">s</span>,
                                        <span x-text="formatTime(selectedSlots[0].start_time)"></span> to
                                        <span x-text="formatTime(selectedSlots[selectedSlots.length - 1].end_time)"></span>
                                    </p>
                                    <p class="mt-1 font-display text-2xl font-semibold text-ink-950 dark:text-white">
                                        ₱<span x-text="totalPrice.toFixed(2)"></span>
                                    </p>

                                    <form method="POST" action="{{ route('book.store', $court) }}" class="mt-4 space-y-3">
                                        @csrf
                                        <template x-for="id in selectedSlotIds" :key="id">
                                            <input type="hidden" name="court_slot_ids[]" :value="id">
                                        </template>

                                        @guest
                                            <div class="flex flex-col gap-2">
                                                <label for="guest_name" class="text-xs font-medium text-ink-700 dark:text-ink-300">Name</label>
                                                <input id="guest_name" name="guest_name" type="text" required value="{{ old('guest_name') }}"
                                                    class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white">
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label for="guest_phone" class="text-xs font-medium text-ink-700 dark:text-ink-300">Phone number</label>
                                                <input id="guest_phone" name="guest_phone" type="tel" required value="{{ old('guest_phone') }}"
                                                    class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white">
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label for="guest_email" class="text-xs font-medium text-ink-700 dark:text-ink-300">Email</label>
                                                <input id="guest_email" name="guest_email" type="email" required value="{{ old('guest_email') }}" placeholder="For your confirmation"
                                                    class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white">
                                            </div>
                                        @endguest

                                        <button
                                            type="submit"
                                            class="w-full rounded-full bg-accent-500 px-6 py-3 text-sm font-semibold text-ink-950 transition-transform active:scale-[0.98] hover:bg-accent-400"
                                        >
                                            Continue to payment
                                        </button>
                                    </form>
                                    @guest
                                        <p class="mt-2 text-center text-xs text-ink-400">
                                            No account needed. <a href="{{ route('login') }}" class="underline hover:text-ink-600 dark:hover:text-ink-200">Log in</a> to save your booking history.
                                        </p>
                                    @endguest
                                    <button type="button" @click="clearSelection()" class="mt-2 w-full text-center text-xs font-medium text-ink-400 hover:text-ink-600 dark:hover:text-ink-200">
                                        Clear selection
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                @error('court_slot_ids')
                    <p class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

</x-layouts.app>
