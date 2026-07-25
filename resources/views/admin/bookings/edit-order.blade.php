<x-layouts.admin :title="'Edit order — '.$order->contactName()">

    @php
        $statusBadge = fn ($b) => match (true) {
            $b->status === 'confirmed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
            $b->status === 'pending_payment' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            $b->status === 'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
            $b->status === 'cancelled' => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
            $b->status === 'completed' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
            $b->status === 'on_hold' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            default => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
        };
        $statusLabel = fn ($b) => str($b->status)->replace('_', ' ')->headline();
        $sessions = $order->bookings->sortBy(fn ($b) => $b->slots->first()?->slot_date);
        $activeCount = $sessions->whereIn('status', ['pending_payment', 'confirmed'])->count();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-100 text-accent-700 dark:bg-accent-950 dark:text-accent-400">
                <i class="ph ph-calendar-check text-xl"></i>
            </span>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Edit order — {{ $order->contactName() }}</h1>
                <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">{{ $activeCount }} active date{{ $activeCount === 1 ? '' : 's' }} in this booking &middot; entered by staff, so it's safe to correct here.</p>
            </div>
        </div>
        <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left text-base"></i>
            Back to bookings
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif
    @error('guest_name')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">{{ $message }}</div>
    @enderror
    @error('sessions')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">{{ $message }}</div>
    @enderror
    @error('booking')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">{{ $message }}</div>
    @enderror

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
        {{-- Guest details: renames every editable session in the order at
        once, so contact info can't drift out of sync between dates. --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                <i class="ph ph-identification-card text-sm"></i> Guest details
            </p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Updates every date in this booking at once.</p>

            <form method="POST" action="{{ route('admin.bookings.edit-order.details', $order) }}" class="mt-3 space-y-3">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Full name</label>
                    <input name="guest_name" type="text" required value="{{ old('guest_name', $order->contactName()) }}"
                        class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Phone</label>
                        <input name="guest_phone" type="tel" pattern="^(09\d{9}|\+639\d{9})$"
                            title="Enter a valid PH mobile number, e.g. 09171234567 or +639171234567"
                            value="{{ old('guest_phone', $order->user->phone ?? $order->guest_phone) }}"
                            class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Email</label>
                        <input name="guest_email" type="email" value="{{ old('guest_email', $order->contactEmail()) }}"
                            class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    </div>
                </div>
                <button type="submit" class="w-full rounded-xl bg-ink-950 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                    Save details for all dates
                </button>
            </form>
        </div>

        {{-- Every date in this booking --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                <i class="ph ph-calendar text-sm"></i> Dates in this booking
            </p>

            <div class="mt-3 space-y-2">
                @foreach ($sessions as $session)
                    @php
                        $sFirst = $session->slots->sortBy('start_time')->first();
                        $sLast = $session->slots->sortBy('start_time')->last();
                        $sHold = $session->status === 'on_hold' ? $session->holds->firstWhere('resolved_at', null) : null;
                    @endphp
                    <div class="rounded-xl border border-ink-100 p-3 dark:border-ink-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-mono text-xs text-ink-400">{{ $session->booking_code }}</span>
                                <span class="ml-1.5 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusBadge($session) }}">{{ $statusLabel($session) }}</span>
                            </div>
                            <p class="text-sm font-semibold text-ink-900 dark:text-ink-100">₱{{ number_format($session->total_price, 2) }}</p>
                        </div>

                        @if ($sFirst)
                            <p class="mt-1.5 text-sm text-ink-700 dark:text-ink-300">
                                {{ \Illuminate\Support\Carbon::parse($sFirst->slot_date)->format('D, M j, Y') }}
                                &middot;
                                {{ \Illuminate\Support\Carbon::parse($sFirst->start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($sLast->end_time)->format('g:i A') }}
                            </p>
                        @elseif ($sHold)
                            <p class="mt-1.5 text-sm text-amber-700 dark:text-amber-400">
                                On hold since {{ $sHold->from_slot_date->format('M j') }} — was {{ \Illuminate\Support\Carbon::parse($sHold->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($sHold->from_end_time)->format('g:i A') }}
                            </p>
                        @endif

                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            @if ($session->isAdminEditable())
                                <a href="{{ route('admin.bookings.edit', $session) }}" class="text-xs font-semibold text-accent-700 hover:text-accent-800 dark:text-accent-400">
                                    Edit this date's hours
                                </a>
                            @endif
                            @if (in_array($session->status, ['pending_payment', 'confirmed'], true))
                                <form
                                    method="POST"
                                    action="{{ route('admin.bookings.cancel-session', $session) }}"
                                    onsubmit="return confirmSubmit(this, { title: 'Remove this date?', text: '{{ $sFirst ? \Illuminate\Support\Carbon::parse($sFirst->slot_date)->format('D, M j, Y') : $session->booking_code }} will be removed from this booking and that time freed up for other customers.', icon: 'warning', confirmButtonText: 'Remove date', confirmButtonColor: '#e11d48' });"
                                >
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400">
                                        Remove this date
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @if ($anchor)
        {{-- Add more dates: same picker as the single-booking edit page,
        reusing addSessions() pointed at whichever session in this order is
        editable - the new dates join the same order automatically. --}}
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
                    // Deliberately a method, not a getter - this object is
                    // spread into the outer x-data literal below, and object
                    // spread evaluates getters into frozen static values
                    // instead of preserving them, silently breaking
                    // reactivity (windowStart updates but the visible dates
                    // never do). A plain method has no such problem.
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
                            const res = await fetch(`{{ url('/api/courts/'.$anchor->court_id.'/slots') }}?date=${this.date}`, { headers: { Accept: 'application/json' } });
                            if (!res.ok) throw new Error();
                            const body = await res.json();
                            this.slots = body.data ?? [];
                        } catch (e) {
                            this.slots = [];
                            this.error = 'Could not load open hours for this date.';
                        }
                        this.loading = false;
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

        <div
            class="mt-6 rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900"
            x-data="{
                ...buildDateRow('picker', {{ \Illuminate\Support\Js::from(now()->toDateString()) }}),
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
                <i class="ph ph-calendar-plus text-sm"></i> Add more dates &amp; times
            </p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">
                Pick times for a date, then just tap another date — it's collected automatically. Save once you've picked everything.
            </p>

            <form method="POST" action="{{ route('admin.bookings.edit.add-sessions', $anchor) }}" class="mt-4 space-y-4" x-ref="form">
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
    @endif

</x-layouts.admin>
