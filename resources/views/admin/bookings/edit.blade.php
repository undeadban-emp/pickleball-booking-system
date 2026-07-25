<x-layouts.admin :title="'Edit '.$booking->booking_code">

    @php
        $slots = $booking->slots->sortBy('start_time')->values();
        $first = $slots->first();
        $last = $slots->last();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-100 text-accent-700 dark:bg-accent-950 dark:text-accent-400">
                <i class="ph ph-pencil-simple text-xl"></i>
            </span>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Edit {{ $booking->booking_code }}</h1>
                <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">Rename the guest, or add/remove hours — this booking was entered by staff, so it's safe to correct here.</p>
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
        @if ($first)
            <div class="ml-auto flex items-center gap-2 rounded-xl bg-ink-50 px-3 py-2 dark:bg-ink-950/60">
                <i class="ph ph-calendar text-ink-400"></i>
                <div>
                    <p class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ \Illuminate\Support\Carbon::parse($first->slot_date)->format('D, M j, Y') }}</p>
                    <p class="text-xs text-ink-500 dark:text-ink-400">{{ \Illuminate\Support\Carbon::parse($first->start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($last->end_time)->format('g:i A') }} &middot; ₱{{ number_format($booking->total_price, 2) }}</p>
                </div>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif
    @error('guest_name')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">{{ $message }}</div>
    @enderror
    @error('court_slot_ids')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">{{ $message }}</div>
    @enderror
    @error('sessions')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">{{ $message }}</div>
    @enderror

    {{-- Shared date-row builder: a date strip (same style as the home page
    booking widget) + that date's live open-hours grid, used by the "Add
    multiple dates & times" picker below. --}}
    <script>
        function buildDateRow(key, initialDate) {
            const pad = (n) => String(n).padStart(2, '0');
            const today = new Date();
            const days = [];
            for (let i = 0; i < 30; i++) {
                const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() + i);
                days.push({
                    dateStr: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
                    weekday: d.toLocaleDateString('en-US', { weekday: 'short' }),
                    day: d.getDate(),
                    month: d.toLocaleDateString('en-US', { month: 'short' }),
                    isToday: i === 0,
                });
            }

            const windowSize = 4;
            const startDate = initialDate && days.some((d) => d.dateStr === initialDate) ? initialDate : days[0].dateStr;
            const startIdx = days.findIndex((d) => d.dateStr === startDate);

            return {
                key,
                date: startDate,
                dateStrip: days,
                windowSize,
                windowStart: startIdx > -1 ? Math.floor(startIdx / windowSize) * windowSize : 0,
                slots: [],
                selected: [],
                loading: false,
                error: null,
                // Deliberately a method, not a getter - this object gets
                // spread into another x-data literal (`{ ...buildDateRow(),
                // ... }`) wherever it's used as one of several date-row
                // pickers, and object spread evaluates getters into frozen
                // static values instead of preserving them, which silently
                // breaks reactivity (windowStart updates but the visible
                // dates never do). A plain method has no such problem.
                visibleDates() {
                    return this.dateStrip.slice(this.windowStart, this.windowStart + this.windowSize);
                },
                prevWindow() {
                    this.windowStart = Math.max(0, this.windowStart - this.windowSize);
                },
                nextWindow() {
                    this.windowStart = Math.min(this.dateStrip.length - this.windowSize, this.windowStart + this.windowSize);
                },
                selectDate(dateStr) {
                    this.date = dateStr;
                    this.load();
                },
                async load(preselect = []) {
                    this.loading = true;
                    this.error = null;
                    this.selected = [];
                    try {
                        const res = await fetch(`{{ url('/api/courts/'.$booking->court_id.'/slots') }}?date=${this.date}`, { headers: { Accept: 'application/json' } });
                        if (!res.ok) throw new Error();
                        const body = await res.json();
                        this.slots = body.data ?? [];
                    } catch (e) {
                        this.slots = [];
                        this.error = 'Could not load open hours for this date.';
                    }
                    this.loading = false;
                    // Re-show any hours already picked for this date on an
                    // earlier visit (see the "Add multiple dates & times"
                    // card's selectDate() override, which reopens a
                    // collected date instead of leaving it un-highlighted).
                    this.selected = preselect.filter((id) => this.slots.some((s) => s.id === id));
                },
                toggle(id) {
                    const i = this.selected.indexOf(id);
                    if (i === -1) this.selected.push(id); else this.selected.splice(i, 1);
                },
                formatTime(t) {
                    const [h, m] = t.split(':');
                    const hour = parseInt(h, 10);
                    const suffix = hour >= 12 ? 'pm' : 'am';
                    const displayHour = hour % 12 === 0 ? 12 : hour % 12;
                    return m === '00' ? `${displayHour}${suffix}` : `${displayHour}:${m}${suffix}`;
                },
                rangeLabel() {
                    const picked = this.slots.filter((s) => this.selected.includes(s.id)).sort((a, b) => a.start_time.localeCompare(b.start_time));
                    if (picked.length === 0) return '';
                    return this.formatTime(picked[0].start_time) + '–' + this.formatTime(picked[picked.length - 1].end_time);
                },
                dateLabel() {
                    const [y, m, d] = this.date.split('-').map(Number);
                    return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                },
            };
        }
    </script>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
        {{-- Guest details --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                <i class="ph ph-identification-card text-sm"></i> Guest details
            </p>

            <form method="POST" action="{{ route('admin.bookings.edit.details', $booking) }}" class="mt-3 space-y-3">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Full name</label>
                    <input name="guest_name" type="text" required value="{{ old('guest_name', $booking->guest_name) }}"
                        class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Phone</label>
                        <input name="guest_phone" type="tel" pattern="^(09\d{9}|\+639\d{9})$"
                            title="Enter a valid PH mobile number, e.g. 09171234567 or +639171234567"
                            value="{{ old('guest_phone', $booking->guest_phone) }}"
                            class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Email</label>
                        <input name="guest_email" type="email" value="{{ old('guest_email', $booking->guest_email) }}"
                            class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    </div>
                </div>
                <button type="submit" class="w-full rounded-xl bg-ink-950 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                    Save details
                </button>
            </form>
        </div>

        {{-- Hours --}}
        <div class="space-y-4">
            <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                    <i class="ph ph-clock text-sm"></i> Booked hours
                </p>

                <div class="mt-3 space-y-1.5">
                    @foreach ($slots as $slot)
                        <div class="flex items-center justify-between gap-3 rounded-lg bg-ink-50 px-3 py-2 dark:bg-ink-950/60">
                            <p class="text-sm font-medium text-ink-800 dark:text-ink-200">
                                {{ \Illuminate\Support\Carbon::parse($slot->slot_date)->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }}
                                <span class="text-xs text-ink-400">₱{{ number_format($slot->price, 2) }}</span>
                            </p>
                            @if ($slots->count() > 1)
                                <form
                                    method="POST"
                                    action="{{ route('admin.bookings.edit.remove-time', $booking) }}"
                                    onsubmit="return confirmSubmit(this, { title: 'Remove this hour?', text: '{{ \Illuminate\Support\Carbon::parse($slot->slot_date)->format('D, M j') }}, {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }} will be removed from this booking and freed up for other customers.', icon: 'warning', confirmButtonText: 'Remove hour', confirmButtonColor: '#e11d48' });"
                                >
                                    @csrf
                                    <input type="hidden" name="court_slot_ids[]" value="{{ $slot->id }}">
                                    <button type="submit" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950" aria-label="Remove this hour">
                                        <i class="ph ph-x text-sm"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Add multiple dates & times: one date-strip + live open-hours grid.
    Pick times for a date, then just tap a different date - whatever was
    selected gets collected automatically (no "add another date" click
    needed) and the grid resets for the new date. Keep going for as many
    dates as needed, then Save once at the end. Nothing is auto-decided:
    admin can only pick hours the grid already shows as open, and saving is
    all-or-nothing - if a collected date's hours were taken by someone else
    in the meantime, the whole batch is rejected with a clear error instead
    of silently dropping that one. --}}
    <div
        class="mt-6 rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900"
        x-data="{
            ...buildDateRow('picker', {{ \Illuminate\Support\Js::from($last?->slot_date->toDateString() ?? now()->toDateString()) }}),
            collected: [],
            commitCurrent() {
                if (this.selected.length > 0) {
                    this.collected.push({
                        date: this.date,
                        dateLabel: this.dateLabel(),
                        rangeLabel: this.rangeLabel(),
                        slotIds: [...this.selected],
                    });
                    this.selected = [];
                }
            },
            selectDate(dateStr) {
                this.commitCurrent();

                // Switching to a date that already has a collected pick
                // reopens it instead of leaving it invisible - pulled back
                // out of the list so it shows highlighted on the grid again
                // and can be edited or toggled off, same as a fresh pick.
                const existing = this.collected.findIndex((item) => item.date === dateStr);
                const reopened = existing !== -1 ? this.collected.splice(existing, 1)[0].slotIds : [];

                this.date = dateStr;
                this.load(reopened);
            },
            removeCollected(i) {
                this.collected.splice(i, 1);
            },
            get canSubmit() {
                return this.collected.length > 0 || this.selected.length > 0;
            },
            submitAll() {
                this.commitCurrent();
                this.$nextTick(() => this.$refs.form.submit());
            },
        }"
        x-init="load()"
    >
        <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
            <i class="ph ph-calendar-plus text-sm"></i> Add multiple dates &amp; times
        </p>
        <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">
            Pick times for a date, then just tap another date — it's collected automatically. Keep going for as many dates as you need, then Save once. Each date becomes its own booking for the same guest.
        </p>

        <form method="POST" action="{{ route('admin.bookings.edit.add-sessions', $booking) }}" class="mt-4 space-y-4" x-ref="form">
            @csrf

            <div class="mt-2 flex items-center gap-1.5">
                <button type="button" @click="prevWindow()" :disabled="windowStart === 0"
                    class="flex h-9 w-7 shrink-0 items-center justify-center rounded-lg border border-ink-200 text-ink-500 hover:border-accent-400 hover:text-accent-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-ink-700 dark:text-ink-400">
                    <i class="ph ph-caret-left text-sm"></i>
                </button>

                <div class="grid flex-1 grid-cols-4 gap-1.5">
                    <template x-for="d in visibleDates()" :key="d.dateStr">
                        <button
                            type="button"
                            @click="selectDate(d.dateStr)"
                            class="relative flex flex-col items-center rounded-xl border px-1 py-2.5 transition-colors"
                            :class="date === d.dateStr
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

                <button type="button" @click="nextWindow()" :disabled="windowStart >= dateStrip.length - windowSize"
                    class="flex h-9 w-7 shrink-0 items-center justify-center rounded-lg border border-ink-200 text-ink-500 hover:border-accent-400 hover:text-accent-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-ink-700 dark:text-ink-400">
                    <i class="ph ph-caret-right text-sm"></i>
                </button>
            </div>

            <template x-if="loading">
                <p class="mt-3 text-sm text-ink-500 dark:text-ink-400">Loading…</p>
            </template>
            <template x-if="!loading && error">
                <p class="mt-3 text-sm text-ink-500 dark:text-ink-400" x-text="error"></p>
            </template>
            <template x-if="!loading && !error && slots.length === 0">
                <p class="mt-3 text-sm text-ink-500 dark:text-ink-400">No open hours on this date.</p>
            </template>

            <template x-if="!loading && !error && slots.length > 0">
                <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <template x-for="slot in slots" :key="slot.id">
                        <button
                            type="button"
                            @click="toggle(slot.id)"
                            class="rounded-xl border px-2 py-2.5 text-sm font-bold transition-all"
                            :class="selected.includes(slot.id) ? 'border-accent-500 bg-accent-500 text-white shadow-sm scale-[1.02]' : 'border-sky-200 bg-sky-50 text-sky-800 hover:border-accent-400 hover:bg-accent-50 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200'"
                            x-text="formatTime(slot.start_time) + '–' + formatTime(slot.end_time)"
                        ></button>
                    </template>
                </div>
            </template>

            <template x-for="(item, i) in collected" :key="'collected-' + i">
                <template x-for="id in item.slotIds" :key="'collected-' + i + '-' + id">
                    <input type="hidden" :name="'sessions[' + i + '][court_slot_ids][]'" :value="id">
                </template>
            </template>

            <template x-if="collected.length > 0 || selected.length > 0">
                <div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-950/60">
                    <p class="text-[11px] font-semibold tracking-wide text-ink-400 uppercase">Will add</p>
                    <div class="mt-1.5 space-y-1">
                        <template x-for="(item, i) in collected" :key="'summary-' + i">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm text-ink-800 dark:text-ink-200">
                                    <span x-text="item.dateLabel"></span>
                                    <span class="text-ink-400">&middot;</span>
                                    <span x-text="item.rangeLabel"></span>
                                </p>
                                <button type="button" @click="removeCollected(i)" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-rose-500 hover:bg-rose-100 dark:hover:bg-rose-950" aria-label="Remove this date">
                                    <i class="ph ph-x text-sm"></i>
                                </button>
                            </div>
                        </template>
                        <p class="text-sm text-ink-500 dark:text-ink-400" x-show="selected.length > 0">
                            <span x-text="dateLabel()"></span>
                            <span class="text-ink-400">&middot;</span>
                            <span x-text="rangeLabel()"></span>
                            <span class="text-[11px] text-ink-400">(selecting…)</span>
                        </p>
                    </div>
                </div>
            </template>

            <button
                type="button"
                @click="submitAll()"
                :disabled="!canSubmit"
                class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-ink-950 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-ink-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400"
            >
                <i class="ph ph-calendar-check"></i>
                Save
            </button>
        </form>
    </div>

</x-layouts.admin>
