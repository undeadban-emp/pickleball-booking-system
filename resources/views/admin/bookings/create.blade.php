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
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
                            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Date</label>
                            <input type="date" x-model="dateStr" :min="minDate" @change="onDateChange()" required
                                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                        </div>
                    </div>

                    <template x-if="selectedCourt && selectedCourt.status === 'maintenance'">
                        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">This court is currently under maintenance.</p>
                    </template>
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
