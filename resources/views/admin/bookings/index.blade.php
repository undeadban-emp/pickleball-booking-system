@php
    // A session is only reschedulable if it hasn't happened yet - once its
    // last slot's date is in the past there's nothing left to move forward.
    $isSessionPast = fn ($booking) => ! $booking->slots->max('slot_date')
        || \Illuminate\Support\Carbon::parse($booking->slots->max('slot_date'))->lt(today());

    // Only a still-live, not-yet-happened session can be picked to move -
    // one that's already rejected/cancelled/completed has nothing left to
    // reschedule, and one tied to an active Open Play room can't have its
    // slots swapped out from under the room. A held booking has zero slots
    // by design, so the past-date check doesn't apply to it - only whether
    // it's still actually on hold matters.
    $isReschedulable = fn ($booking) => $booking->status === 'on_hold'
        ? $booking->holds->whereNull('resolved_at')->isNotEmpty()
        : in_array($booking->status, ['pending_payment', 'confirmed'], true)
            && ! $isSessionPast($booking)
            && ! $booking->openPlayRoomCourt()->exists();

    // Only a currently confirmed booking (not already on hold, rejected,
    // etc.) not tied to an active Open Play room can be put on hold.
    $isHoldable = fn ($booking) => $booking->status === 'confirmed' && ! $booking->openPlayRoomCourt()->exists();

    // Reschedules now update the same booking in place instead of creating
    // a replacement row - this is the most recent move, if any, so it can
    // be shown as a small note under the session's date/time.
    $rescheduleNote = fn ($booking) => $booking->relationLoaded('rescheduleLogs') ? $booking->rescheduleLogs->last() : null;

    // A partial reschedule splits a booking's untouched hours off into
    // sibling booking row(s) that keep their original date/time - these
    // small helpers surface that relationship on both sides so it's clear
    // why there are multiple sessions under one order.
    $splitFromNote = fn ($booking) => $booking->relationLoaded('splitFrom') ? $booking->splitFrom : null;
    $splitSiblingsNote = fn ($booking) => $booking->relationLoaded('splitSiblings') ? $booking->splitSiblings : collect();

    // When the booking a session split off from is itself on hold, this
    // finds what time was actually held - so "split off from X (on hold)"
    // can say when, not just that it happened.
    $splitFromHoldNote = fn ($booking) => $booking && $booking->status === 'on_hold' && $booking->relationLoaded('holds')
        ? $booking->holds->whereNull('resolved_at')->first()
        : null;

    // A held session has zero slots, so there's nothing for the normal
    // date/time display to read - this surfaces what it USED to be instead,
    // wherever a session's slots might be empty because it's on hold.
    $holdNote = fn ($booking) => $booking->relationLoaded('holds') ? $booking->holds->whereNull('resolved_at')->first() : null;

    // A pending_payment booking with a GCash reference already submitted is
    // sitting in the admin review queue, not waiting on the customer anymore
    // - give it its own color/label instead of looking identical to "customer
    // hasn't paid yet".
    $statusBadge = fn ($booking) => match (true) {
        $booking->status === 'pending_payment' && $booking->hasSubmittedPayment() => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300',
        $booking->status === 'confirmed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
        $booking->status === 'pending_payment' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        $booking->status === 'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
        $booking->status === 'cancelled' => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
        $booking->status === 'completed' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        $booking->status === 'on_hold' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        default => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
    };

    $statusLabel = fn ($booking) => $booking->status === 'pending_payment' && $booking->hasSubmittedPayment()
        ? 'Awaiting Approval'
        : str($booking->status)->replace('_', ' ')->headline();
@endphp

<x-layouts.admin :title="'Bookings'">

    <div
        x-data="{
            activeId: null,
            expandedOrders: [],
            toggleOrder(id) {
                this.expandedOrders = this.expandedOrders.includes(id)
                    ? this.expandedOrders.filter((x) => x !== id)
                    : [...this.expandedOrders, id];
            },
            lastId: {{ $bookings->max('id') ?? 0 }},
            newCount: 0,
            // Snapshot of what's currently on screen for each visible
            // booking, so the poll can tell a customer just submitted a
            // reference/proof on an already-listed pending booking apart
            // from nothing having changed - a payment submission doesn't
            // create a new row, so watching only for new ids would leave
            // the table stuck showing Not submitted until someone
            // refreshed by hand.
            watchState: @js($bookings->mapWithKeys(fn ($b) => [$b->id => ['status' => $b->status, 'has_reference' => filled($b->gcash_reference), 'has_proof' => filled($b->payment_proof_path)]])),
            poll() {
                const watchIds = Object.keys(this.watchState).join(',');

                fetch('{{ route('admin.bookings.latest') }}?last_id=' + this.lastId + '&watch_ids=' + watchIds, { headers: { Accept: 'application/json' } })
                    .then(res => res.ok ? res.json() : null)
                    .then(payload => {
                        if (!payload) return;

                        let changed = false;

                        if (payload.data && payload.data.length) {
                            changed = true;
                            this.newCount += payload.data.length;
                            this.lastId = payload.data[payload.data.length - 1].id;
                        }

                        for (const u of (payload.updates || [])) {
                            const prev = this.watchState[u.id];
                            if (prev && (prev.status !== u.status || prev.has_reference !== u.has_reference || prev.has_proof !== u.has_proof)) {
                                changed = true;
                                this.newCount += 1;
                            }
                            this.watchState[u.id] = { status: u.status, has_reference: u.has_reference, has_proof: u.has_proof };
                        }

                        // Don't yank the page out from under an admin who's
                        // mid-decision on an Approve/Reject/Cancel confirm
                        // dialog - the updates banner is already up
                        // (newCount was bumped above either way), so they
                        // can tap it once they're done instead of losing
                        // the dialog to a surprise reload.
                        if (changed && this.activeId === null && !window.__confirmDialogOpen) {
                            window.location.reload();
                        }
                    })
                    .catch(() => {});
            }
        }"
        x-init="setInterval(() => poll(), 5000)"
        @keydown.escape.window="activeId = null"
    >

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Bookings</h1>
        <a href="{{ route('admin.bookings.create') }}" class="flex items-center gap-1.5 rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
            <i class="ph ph-plus text-base"></i>
            New booking
        </a>
    </div>

    <button
        type="button"
        x-show="newCount > 0"
        x-cloak
        x-transition
        @click="window.location.reload()"
        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-accent-300 bg-accent-50 px-4 py-3 text-sm font-semibold text-accent-800 transition-colors hover:bg-accent-100 dark:border-accent-800 dark:bg-accent-950 dark:text-accent-200 dark:hover:bg-accent-900"
    >
        <i class="ph ph-bell-ringing"></i>
        <span x-text="newCount"></span> update<span x-show="newCount > 1">s</span> came in (new bookings or payments submitted). Tap to refresh.
    </button>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif
    @error('booking')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            {{ $message }}
        </div>
    @enderror

    {{-- Filters --}}
    <form method="GET" class="mt-6 flex flex-wrap items-end gap-3 rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Status</label>
            <select name="status" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-800 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-200">
                <option value="">All</option>
                @foreach (['pending_payment' => 'Pending payment', 'confirmed' => 'Confirmed', 'on_hold' => 'On hold', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Court</label>
            <select name="court_id" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-800 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-200">
                <option value="">All courts</option>
                @foreach ($courts as $court)
                    <option value="{{ $court->id }}" @selected((string) ($filters['court_id'] ?? '') === (string) $court->id)>{{ $court->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-1 min-w-[180px] flex-col gap-1">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Search</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Code, name, or phone"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-800 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-200">
        </div>

        <button type="submit" class="rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
            Filter
        </button>
        @if (array_filter($filters))
            <a href="{{ route('admin.bookings.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">Clear</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="mt-4 overflow-x-auto rounded-2xl border border-ink-200 dark:border-ink-800">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="bg-ink-100 text-xs font-medium tracking-wide text-ink-500 uppercase dark:bg-ink-800 dark:text-ink-400">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Court</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100 bg-white dark:divide-ink-800 dark:bg-ink-900">
                @forelse ($bookings as $booking)
                    @php $isOrder = $booking->bookingOrder && $booking->bookingOrder->bookings_count > 1; @endphp
                    <tr
                        @click="activeId = {{ $booking->id }}"
                        class="cursor-pointer transition-colors hover:bg-ink-50 dark:hover:bg-ink-800/50"
                    >
                        <td class="px-4 py-3 font-mono text-xs text-ink-600 dark:text-ink-400">
                            {{ $booking->booking_code }}
                            @if ($isOrder)
                                <span class="ml-1 font-sans text-ink-400">+{{ $booking->bookingOrder->bookings_count - 1 }} more</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-ink-900 dark:text-ink-100">{{ $booking->contactName() }}</p>
                            @if ($booking->isGuestBooking())
                                <p class="text-xs text-ink-500 dark:text-ink-500">{{ $booking->guest_phone }} · Guest</p>
                            @endif
                            @if (! $isOrder && $rescheduleNote($booking))
                                @php $__log = $rescheduleNote($booking); @endphp
                                <p class="text-xs font-medium text-accent-700 dark:text-accent-400">
                                    <i class="ph ph-arrow-clockwise text-xs"></i>
                                    Rescheduled from {{ $__log->from_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($__log->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($__log->from_end_time)->format('g:i A') }}
                                    to {{ $__log->to_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($__log->to_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($__log->to_end_time)->format('g:i A') }}
                                </p>
                            @endif
                            @if ($isOrder)
                                <button
                                    type="button"
                                    @click.stop="toggleOrder({{ $booking->bookingOrder->id }})"
                                    class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-sky-700 hover:text-sky-800 dark:text-sky-400"
                                >
                                    <i class="ph ph-shopping-cart-simple text-xs"></i>
                                    Order · {{ $booking->bookingOrder->bookings_count }} sessions
                                    <i class="ph text-xs transition-transform" :class="expandedOrders.includes({{ $booking->bookingOrder->id }}) ? 'ph-caret-up' : 'ph-caret-down'"></i>
                                </button>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-ink-700 dark:text-ink-300">{{ $booking->court->name }}</td>
                        <td class="px-4 py-3 text-ink-700 dark:text-ink-300">
                            @if ($booking->gcash_reference)
                                <span class="inline-flex items-center gap-1 font-mono text-xs text-emerald-700 dark:text-emerald-400">
                                    <i class="ph ph-check-circle text-sm"></i>
                                    {{ $booking->gcash_reference }}
                                </span>
                            @elseif ($booking->paymentProofUrl())
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                    <i class="ph ph-check-circle text-sm"></i>
                                    Proof of payment
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                                    <i class="ph ph-x-circle text-sm"></i>
                                    Not submitted
                                </span>
                            @endif
                            @if ($booking->paymentProofUrl())
                                <i class="ph ph-image ml-1 text-sm text-accent-700 dark:text-accent-400" title="Proof of payment attached"></i>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($isOrder ? $booking->bookingOrder->total_price : $booking->total_price, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge($booking) }}">
                                {{ $statusLabel($booking) }}
                            </span>
                            @if ($booking->status === 'cancelled')
                                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $booking->cancellationSummary() }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3" @click.stop>
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" @click="activeId = {{ $booking->id }}" class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400" title="View details">
                                    <i class="ph ph-eye text-base"></i>
                                </button>
                                @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                                    @if ($isOrder)
                                        @if ($booking->bookingOrder->bookings->contains($isReschedulable))
                                            <a
                                                href="{{ route('admin.bookings.reschedule.select', $booking->bookingOrder) }}"
                                                class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400"
                                                title="Pick a session to reschedule"
                                            >
                                                <i class="ph ph-arrow-clockwise text-base"></i>
                                            </a>
                                        @endif
                                        @if ($booking->bookingOrder->bookings->contains($isHoldable))
                                            <a
                                                href="{{ route('admin.bookings.hold.select', $booking->bookingOrder) }}"
                                                class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-amber-400 hover:text-amber-700 dark:border-ink-700 dark:text-ink-400"
                                                title="Pick a session to hold"
                                            >
                                                <i class="ph ph-pause-circle text-base"></i>
                                            </a>
                                        @endif
                                    @else
                                        @if ($isReschedulable($booking))
                                            <a
                                                href="{{ route('admin.bookings.reschedule.edit', $booking) }}"
                                                class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400"
                                                title="Reschedule this booking"
                                            >
                                                <i class="ph ph-arrow-clockwise text-base"></i>
                                            </a>
                                        @endif
                                        @if ($isHoldable($booking))
                                            <a
                                                href="{{ route('admin.bookings.hold.edit', $booking) }}"
                                                class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-amber-400 hover:text-amber-700 dark:border-ink-700 dark:text-ink-400"
                                                title="Put this booking on hold"
                                            >
                                                <i class="ph ph-pause-circle text-base"></i>
                                            </a>
                                        @endif
                                    @endif
                                @endif
                                @if ($booking->status === 'pending_payment' && (auth()->user()->isAdmin() || auth()->user()->isStaff()))
                                    @php
                                        $orderNote = $booking->bookingOrder ? " This covers all {$booking->bookingOrder->bookings_count} sessions in the order." : '';
                                    @endphp
                                    <form
                                        method="POST"
                                        action="{{ route('admin.bookings.approve', $booking) }}"
                                        onsubmit="return confirmSubmit(this, { title: 'Approve this booking?', text: 'The customer will be notified that their booking is confirmed.{{ $orderNote }}', icon: 'question', confirmButtonText: 'Approve', confirmButtonColor: '#10b981' });"
                                    >
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-600">Approve</button>
                                    </form>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.bookings.reject', $booking) }}"
                                        onsubmit="return confirmSubmit(this, { title: 'Reject this booking?', text: 'The customer will be notified that their payment was not confirmed.{{ $orderNote }}', icon: 'warning', confirmButtonText: 'Reject', confirmButtonColor: '#e11d48', input: 'textarea', inputLabel: 'Reason (optional, shown to the customer)', inputPlaceholder: 'e.g. Payment could not be verified', inputName: 'reason' });"
                                    >
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-rose-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-600">Reject</button>
                                    </form>
                                @elseif ($booking->status === 'confirmed' && (auth()->user()->isAdmin() || auth()->user()->isStaff()))
                                    <form
                                        method="POST"
                                        action="{{ route('admin.bookings.cancel', $booking) }}"
                                        onsubmit="return confirmSubmit(this, { title: 'Cancel this booking?', text: 'This will free up the slot and notify the customer.', icon: 'warning', confirmButtonText: 'Cancel booking', confirmButtonColor: '#e11d48' });"
                                    >
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-700 hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-300">Cancel</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if ($isOrder)
                        @php
                            $canManage = auth()->user()->isAdmin() || auth()->user()->isStaff();
                        @endphp
                        <tr x-show="expandedOrders.includes({{ $booking->bookingOrder->id }})" x-cloak>
                            <td colspan="7" class="bg-ink-50 px-4 py-3 dark:bg-ink-800/40">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Sessions in this order</p>
                                    <a href="{{ route('order.public', $booking->bookingOrder->receipt_token) }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-medium text-accent-700 hover:text-accent-800 dark:text-accent-400">
                                        Open combined receipt <i class="ph ph-arrow-square-out"></i>
                                    </a>
                                </div>

                                <ul class="mt-2 space-y-1.5">
                                    @foreach ($booking->bookingOrder->bookings as $session)
                                        @php
                                            $sessionPast = $isSessionPast($session);
                                            $log = $rescheduleNote($session);
                                            $splitFrom = $splitFromNote($session);
                                            $splitSiblings = $splitSiblingsNote($session);
                                            $hold = $session->status === 'on_hold' ? $holdNote($session) : null;
                                        @endphp
                                        <li class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm {{ $sessionPast ? 'opacity-50' : '' }}">
                                            <span class="font-mono text-xs text-ink-500 dark:text-ink-400">{{ $session->booking_code }}</span>
                                            @if ($session->slots->isNotEmpty())
                                                <span class="text-ink-700 dark:text-ink-300">
                                                    @foreach ($session->slots->sortBy('start_time') as $slot)
                                                        {{ \Illuminate\Support\Carbon::parse($slot->slot_date)->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }}@if (! $loop->last), @endif
                                                    @endforeach
                                                </span>
                                            @elseif ($hold)
                                                <span class="text-amber-700 dark:text-amber-400">
                                                    was {{ $hold->from_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($hold->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($hold->from_end_time)->format('g:i A') }}
                                                </span>
                                            @endif
                                            <span class="font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($session->total_price, 2) }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusBadge($session) }}">{{ $statusLabel($session) }}</span>
                                            @if ($log)
                                                <span class="text-[10px] text-ink-400" title="Rescheduled {{ $log->created_at->format('M j, g:i A') }}">
                                                    ↻ was {{ $log->from_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($log->from_start_time)->format('g:i A') }}
                                                </span>
                                            @endif
                                            @if ($hold && $hold->reason)
                                                <span class="text-[10px] text-ink-400" title="Held {{ $hold->created_at->format('M j, g:i A') }}">
                                                    ⏸ {{ $hold->reason }}
                                                </span>
                                            @endif
                                            @if ($splitFrom)
                                                @php $splitFromHold = $splitFromHoldNote($splitFrom); @endphp
                                                <span class="text-[10px] text-ink-400">
                                                    ✂ split off from {{ $splitFrom->booking_code }}
                                                    @if ($splitFromHold)
                                                        (on hold: {{ $splitFromHold->from_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($splitFromHold->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($splitFromHold->from_end_time)->format('g:i A') }})
                                                    @endif
                                                    — kept at its original time
                                                </span>
                                            @endif
                                            @if ($splitSiblings->isNotEmpty())
                                                <span class="text-[10px] text-ink-400">✂ {{ $splitSiblings->count() }} hour(s) split off, kept booked ({{ $splitSiblings->pluck('booking_code')->implode(', ') }})</span>
                                            @endif
                                            @if ($sessionPast && $session->status !== 'cancelled')
                                                <span class="text-[10px] font-semibold tracking-wide text-ink-400 uppercase">Already passed</span>
                                            @endif
                                            @if ($canManage && $isReschedulable($session))
                                                <a href="{{ route('admin.bookings.reschedule.edit', $session) }}" class="text-[10px] font-semibold text-accent-700 hover:text-accent-800 dark:text-accent-400">
                                                    Reschedule
                                                </a>
                                            @endif
                                            @if ($canManage && $isHoldable($session))
                                                <a href="{{ route('admin.bookings.hold.edit', $session) }}" class="text-[10px] font-semibold text-amber-700 hover:text-amber-800 dark:text-amber-400">
                                                    Hold
                                                </a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-ink-500 dark:text-ink-400">No bookings match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $bookings->links() }}
    </div>

    {{-- Detail sheet --}}
    <div
        x-show="activeId !== null"
        x-cloak
        class="fixed inset-0 z-50 bg-ink-950/40"
        x-transition.opacity
        @click="activeId = null"
    ></div>

    <div
        x-show="activeId !== null"
        x-cloak
        class="fixed inset-y-0 right-0 z-50 w-full overflow-y-auto bg-white shadow-2xl lg:w-1/2 dark:bg-ink-900"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
    >
        @foreach ($bookings as $booking)
            <div x-show="activeId === {{ $booking->id }}">
                <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4 dark:border-ink-800">
                    <div>
                        <p class="font-display text-lg font-semibold text-ink-950 dark:text-white">{{ $booking->booking_code }}</p>
                        <span class="mt-1 inline-block rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge($booking) }}">
                            {{ $statusLabel($booking) }}
                        </span>
                    </div>
                    <button type="button" @click="activeId = null" class="rounded-lg p-2 text-ink-400 hover:bg-ink-100 hover:text-ink-800 dark:hover:bg-ink-800 dark:hover:text-white" aria-label="Close">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>

                <div class="space-y-4 px-5 py-5">
                    {{-- Customer --}}
                    <div class="rounded-2xl border border-ink-100 p-4 dark:border-ink-800">
                        <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                            <i class="ph ph-user text-sm"></i> Customer
                        </p>
                        <p class="mt-2 font-medium text-ink-900 dark:text-ink-100">{{ $booking->contactName() }}</p>
                        <p class="text-sm text-ink-500 dark:text-ink-400">
                            {{ $booking->user->phone ?? $booking->guest_phone ?? 'No phone' }}
                            @if ($booking->isGuestBooking())
                                · Guest
                            @endif
                        </p>
                        @if ($booking->user->email ?? $booking->guest_email)
                            <p class="text-sm text-ink-500 dark:text-ink-400">{{ $booking->user->email ?? $booking->guest_email }}</p>
                        @endif
                        @if (! $booking->bookingOrder && $rescheduleNote($booking))
                            @php $__log = $rescheduleNote($booking); @endphp
                            <p class="mt-2 flex items-start gap-1.5 rounded-lg bg-accent-50 px-2.5 py-1.5 text-xs font-medium text-accent-800 dark:bg-accent-950 dark:text-accent-300">
                                <i class="ph ph-arrow-clockwise mt-0.5 shrink-0"></i>
                                <span>
                                    Rescheduled from {{ $__log->from_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($__log->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($__log->from_end_time)->format('g:i A') }}
                                    to {{ $__log->to_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($__log->to_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($__log->to_end_time)->format('g:i A') }} — no new payment taken
                                </span>
                            </p>
                        @endif
                        @if ($booking->bookingOrder)
                            <p class="mt-2 flex items-center gap-1.5 rounded-lg bg-sky-50 px-2.5 py-1.5 text-xs font-medium text-sky-800 dark:bg-sky-950 dark:text-sky-300">
                                <i class="ph ph-shopping-cart-simple shrink-0"></i>
                                Part of an order — {{ $booking->bookingOrder->bookings_count }} sessions, one combined payment
                            </p>
                        @endif
                    </div>

                    {{-- Court & time --}}
                    <div class="rounded-2xl border border-ink-100 p-4 dark:border-ink-800">
                        <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                            <i class="ph ph-calendar text-sm"></i> Court &amp; time
                        </p>
                        @if ($booking->bookingOrder && $booking->bookingOrder->bookings_count > 1)
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($booking->bookingOrder->bookings as $session)
                                    @php
                                        $log = $rescheduleNote($session);
                                        $splitFrom = $splitFromNote($session);
                                        $splitSiblings = $splitSiblingsNote($session);
                                        $firstSlot = $session->slots->sortBy('start_time')->first();
                                        $lastSlot = $session->slots->sortBy('start_time')->last();
                                        $hold = $session->status === 'on_hold' ? $holdNote($session) : null;
                                    @endphp
                                    <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3 dark:border-ink-800 dark:bg-ink-950/40">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-mono text-xs text-ink-400">{{ $session->booking_code }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusBadge($session) }}">{{ $statusLabel($session) }}</span>
                                        </div>
                                        @if ($firstSlot)
                                            <p class="mt-1.5 font-display text-sm font-semibold text-ink-950 dark:text-white">
                                                {{ \Illuminate\Support\Carbon::parse($firstSlot->slot_date)->format('D, M j, Y') }}
                                            </p>
                                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-ink-600 dark:text-ink-400">
                                                <i class="ph ph-clock text-sm"></i>
                                                {{ \Illuminate\Support\Carbon::parse($firstSlot->start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($lastSlot->end_time)->format('g:i A') }}
                                            </p>
                                        @elseif ($hold)
                                            <p class="mt-1.5 text-sm font-semibold text-amber-700 dark:text-amber-400">
                                                On hold since {{ $hold->from_slot_date->format('M j') }}
                                            </p>
                                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-ink-600 dark:text-ink-400">
                                                <i class="ph ph-clock text-sm"></i>
                                                was {{ \Illuminate\Support\Carbon::parse($hold->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($hold->from_end_time)->format('g:i A') }}
                                            </p>
                                            @if ($hold->reason)
                                                <p class="mt-0.5 text-xs text-ink-400">{{ $hold->reason }}</p>
                                            @endif
                                        @endif
                                        <p class="mt-0.5 text-xs text-ink-400">{{ $session->court->name ?? $booking->court->name }} &middot; ₱{{ number_format($session->total_price, 2) }}</p>
                                        @if ($log)
                                            <p class="mt-1.5 text-xs text-ink-400">
                                                ↻ Rescheduled from {{ $log->from_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($log->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($log->from_end_time)->format('g:i A') }}
                                            </p>
                                        @endif
                                        @if ($splitFrom)
                                            @php $splitFromHold = $splitFromHoldNote($splitFrom); @endphp
                                            <p class="mt-1.5 text-xs text-ink-400">
                                                ✂ Split off from {{ $splitFrom->booking_code }}
                                                @if ($splitFromHold)
                                                    (on hold: {{ $splitFromHold->from_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($splitFromHold->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($splitFromHold->from_end_time)->format('g:i A') }})
                                                @endif
                                                — kept at its original time
                                            </p>
                                        @endif
                                        @if ($splitSiblings->isNotEmpty())
                                            <p class="mt-1.5 text-xs text-ink-400">✂ {{ $splitSiblings->count() }} hour(s) split off, kept booked — see {{ $splitSiblings->pluck('booking_code')->implode(', ') }}</p>
                                        @endif
                                        @if ((auth()->user()->isAdmin() || auth()->user()->isStaff()) && $isReschedulable($session))
                                            <a href="{{ route('admin.bookings.reschedule.edit', $session) }}" class="mt-2 inline-flex items-center gap-1 rounded-lg border border-accent-300 bg-white px-2 py-1 text-xs font-semibold text-accent-700 hover:border-accent-400 hover:bg-accent-50 dark:border-accent-800 dark:bg-transparent dark:text-accent-400 dark:hover:bg-accent-950">
                                                <i class="ph ph-arrow-clockwise"></i> Reschedule
                                            </a>
                                        @endif
                                        @if ((auth()->user()->isAdmin() || auth()->user()->isStaff()) && $isHoldable($session))
                                            <a href="{{ route('admin.bookings.hold.edit', $session) }}" class="mt-2 ml-1 inline-flex items-center gap-1 rounded-lg border border-amber-300 bg-white px-2 py-1 text-xs font-semibold text-amber-700 hover:border-amber-400 hover:bg-amber-50 dark:border-amber-800 dark:bg-transparent dark:text-amber-400 dark:hover:bg-amber-950">
                                                <i class="ph ph-pause-circle"></i> Hold
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-3 border-t border-ink-100 pt-3 font-display text-xl font-semibold text-ink-950 dark:border-ink-800 dark:text-white">₱{{ number_format($booking->bookingOrder->total_price, 2) }} <span class="text-sm font-normal text-ink-400">total</span></p>
                        @else
                            @php
                                $firstSlot = $booking->slots->sortBy('start_time')->first();
                                $lastSlot = $booking->slots->sortBy('start_time')->last();
                            @endphp
                            <p class="mt-2 font-medium text-ink-900 dark:text-ink-100">{{ $booking->court->name }}</p>
                            @if ($firstSlot)
                                <p class="mt-1 font-display text-sm font-semibold text-ink-950 dark:text-white">
                                    {{ \Illuminate\Support\Carbon::parse($firstSlot->slot_date)->format('D, M j, Y') }}
                                </p>
                                <p class="mt-0.5 flex items-center gap-1.5 text-sm text-ink-600 dark:text-ink-400">
                                    <i class="ph ph-clock text-sm"></i>
                                    {{ \Illuminate\Support\Carbon::parse($firstSlot->start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($lastSlot->end_time)->format('g:i A') }}
                                </p>
                            @elseif ($booking->status === 'on_hold' && $booking->holds->first())
                                @php $__hold = $booking->holds->first(); @endphp
                                <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                                    On hold since {{ $__hold->from_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($__hold->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($__hold->from_end_time)->format('g:i A') }}
                                </p>
                                @if ($__hold->reason)
                                    <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{{ $__hold->reason }}</p>
                                @endif
                            @endif
                            <p class="mt-2 border-t border-ink-100 pt-2 font-display text-xl font-semibold text-ink-950 dark:border-ink-800 dark:text-white">₱{{ number_format($booking->total_price, 2) }}</p>
                        @endif
                    </div>

                    {{-- Payment --}}
                    <div class="rounded-2xl border border-ink-100 p-4 dark:border-ink-800">
                        <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                            <i class="ph ph-receipt text-sm"></i> Payment
                        </p>
                        @if ($booking->gcash_reference)
                            <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                                <i class="ph ph-check-circle text-sm"></i> Reference submitted
                            </p>
                            <p class="mt-2 font-mono text-sm text-ink-900 dark:text-ink-100">{{ $booking->gcash_reference }}</p>
                            <p class="text-xs text-ink-500 dark:text-ink-400">Submitted {{ $booking->gcash_submitted_at?->format('M j, g:i A') }}</p>
                        @elseif ($booking->paymentProofUrl())
                            <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                                <i class="ph ph-check-circle text-sm"></i> Proof of payment submitted
                            </p>
                            <p class="text-xs text-ink-500 dark:text-ink-400">Submitted {{ $booking->gcash_submitted_at?->format('M j, g:i A') }}</p>
                        @else
                            <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-400">
                                <i class="ph ph-x-circle text-sm"></i> No reference submitted yet
                            </p>
                        @endif

                        @if ($booking->paymentProofUrl())
                            <div x-data="{ lightbox: false, zoomed: false }">
                                <button type="button" @click="lightbox = true" class="mt-2 block">
                                    <img src="{{ $booking->paymentProofUrl() }}" alt="Proof of payment" class="max-h-64 cursor-zoom-in rounded-xl border border-ink-100 object-contain transition-opacity hover:opacity-90 dark:border-ink-800">
                                </button>

                                <div
                                    x-show="lightbox"
                                    x-cloak
                                    x-transition.opacity
                                    @click="lightbox = false; zoomed = false"
                                    @keydown.escape.window="lightbox = false; zoomed = false"
                                    class="fixed inset-0 z-60 bg-ink-950/80 p-4"
                                    :class="zoomed ? 'overflow-auto' : 'flex items-center justify-center'"
                                >
                                    <div class="fixed top-4 right-4 z-10 flex items-center gap-2">
                                        <button type="button" @click.stop="zoomed = !zoomed" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" :aria-label="zoomed ? 'Zoom out' : 'Zoom in'">
                                            <i class="ph text-xl" :class="zoomed ? 'ph-magnifying-glass-minus' : 'ph-magnifying-glass-plus'"></i>
                                        </button>
                                        <button type="button" @click="lightbox = false; zoomed = false" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Close">
                                            <i class="ph ph-x text-xl"></i>
                                        </button>
                                    </div>
                                    <img
                                        src="{{ $booking->paymentProofUrl() }}"
                                        alt="Proof of payment"
                                        @click.stop="zoomed = !zoomed"
                                        class="rounded-2xl shadow-2xl transition-all"
                                        :class="zoomed ? 'my-8 max-w-none cursor-zoom-out mx-auto' : 'max-h-[85vh] max-w-full object-contain cursor-zoom-in'"
                                    >
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($booking->rejection_reason || $booking->status === 'cancelled')
                        <div class="rounded-2xl border border-ink-100 p-4 dark:border-ink-800">
                            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                                <i class="ph ph-note text-sm"></i> Note
                            </p>
                            <p class="mt-2 text-sm text-ink-600 dark:text-ink-400">{{ $booking->rejection_reason ?? $booking->cancellationSummary() }}</p>
                        </div>
                    @endif

                    @php
                        $history = $booking->statusLogs
                            ->map(function ($log) use ($booking) {
                                $label = str($log->to_status)->replace('_', ' ')->headline();

                                // "On Hold" alone doesn't say which hours -
                                // find the hold record from around the same
                                // moment and append what time was held.
                                if ($log->to_status === 'on_hold') {
                                    $hold = $booking->holds->sortBy(fn ($h) => abs($h->created_at->diffInSeconds($log->created_at)))->first();
                                    if ($hold) {
                                        $label .= ': '.$hold->from_slot_date->format('M j').', '.\Illuminate\Support\Carbon::parse($hold->from_start_time)->format('g:i A').'–'.\Illuminate\Support\Carbon::parse($hold->from_end_time)->format('g:i A');
                                    }
                                }

                                return [
                                    'at' => $log->created_at,
                                    'changedBy' => $log->changedBy,
                                    'label' => $label.($log->note ? " — {$log->note}" : ''),
                                ];
                            })
                            ->concat($booking->rescheduleLogs->map(fn ($log) => [
                                'at' => $log->created_at,
                                'changedBy' => $log->changedBy,
                                'label' => "Rescheduled from {$log->from_slot_date->format('M j')}, ".\Illuminate\Support\Carbon::parse($log->from_start_time)->format('g:i A').'–'.\Illuminate\Support\Carbon::parse($log->from_end_time)->format('g:i A')." to {$log->to_slot_date->format('M j')}, ".\Illuminate\Support\Carbon::parse($log->to_start_time)->format('g:i A').'–'.\Illuminate\Support\Carbon::parse($log->to_end_time)->format('g:i A'),
                            ]))
                            ->sortByDesc('at');
                    @endphp
                    @if ($history->isNotEmpty())
                        <div class="rounded-2xl border border-ink-100 p-4 dark:border-ink-800">
                            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                                <i class="ph ph-clock-counter-clockwise text-sm"></i> History
                            </p>
                            <ul class="mt-2 space-y-2 border-l border-ink-100 pl-3 dark:border-ink-800">
                                @foreach ($history as $entry)
                                    <li class="text-xs">
                                        <p class="font-medium text-ink-700 dark:text-ink-200">{{ $entry['label'] }}</p>
                                        <p class="mt-0.5 text-ink-400 dark:text-ink-500">
                                            {{ $entry['at']->format('M j, g:i A') }}
                                            @if ($entry['changedBy'])
                                                &middot; by {{ $entry['changedBy']->name }}
                                            @endif
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($booking->status === 'completed' && $booking->checked_in_at !== null)
                        <div class="rounded-2xl border border-ink-100 p-4 dark:border-ink-800">
                            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                                <i class="ph ph-trophy text-sm"></i> Match
                            </p>

                            @if ($booking->matches->isNotEmpty())
                                <ul class="mt-2 space-y-1.5">
                                    @foreach ($booking->matches->sortByDesc('id') as $match)
                                        <li>
                                            <a href="{{ route('admin.matches.show', $match) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-accent-700 hover:text-accent-800 dark:text-accent-400">
                                                <i class="ph {{ in_array($match->status, ['verifying', 'completed']) ? 'ph-trophy' : 'ph-play-circle' }}"></i>
                                                {{ in_array($match->status, ['verifying', 'completed']) ? 'View results' : 'Score match' }}
                                                <span class="text-xs font-normal text-ink-400">({{ str($match->status)->headline() }})</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('admin.matches.create', $booking) }}" class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-600 hover:border-ink-400 hover:text-ink-900 dark:border-ink-700 dark:text-ink-400 dark:hover:text-white">
                                    <i class="ph ph-plus"></i>
                                    Add another match
                                </a>
                            @else
                                <a href="{{ route('admin.matches.create', $booking) }}" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                                    <i class="ph ph-plus"></i>
                                    Add match
                                </a>
                            @endif
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="sticky bottom-0 -mx-5 -mb-5 space-y-3 border-t border-ink-100 bg-white/95 px-5 py-4 backdrop-blur dark:border-ink-800 dark:bg-ink-900/95">
                        @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                            @if ($booking->status === 'pending_payment')
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('admin.bookings.approve', $booking) }}" class="flex-1"
                                        onsubmit="return confirmSubmit(this, { title: 'Approve this booking?', text: 'The customer will be notified that their booking is confirmed.', icon: 'question', confirmButtonText: 'Approve', confirmButtonColor: '#10b981' });"
                                    >
                                        @csrf
                                        <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600">
                                            <i class="ph ph-check-circle text-base"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.bookings.reject', $booking) }}" class="flex-1"
                                        onsubmit="return confirmSubmit(this, { title: 'Reject this booking?', text: 'The customer will be notified that their payment was not confirmed.', icon: 'warning', confirmButtonText: 'Reject', confirmButtonColor: '#e11d48', input: 'textarea', inputLabel: 'Reason (optional, shown to the customer)', inputPlaceholder: 'e.g. Payment could not be verified', inputName: 'reason' });"
                                    >
                                        @csrf
                                        <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-rose-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-600">
                                            <i class="ph ph-x-circle text-base"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            @elseif ($booking->status === 'confirmed')
                                <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}"
                                    onsubmit="return confirmSubmit(this, { title: 'Cancel this booking?', text: 'This will free up the slot and notify the customer.', icon: 'warning', confirmButtonText: 'Cancel booking', confirmButtonColor: '#e11d48' });"
                                >
                                    @csrf
                                    <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-4 py-2.5 text-sm font-semibold text-ink-700 hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-300">
                                        <i class="ph ph-prohibit text-base"></i> Cancel booking
                                    </button>
                                </form>
                            @endif
                        @endif

                        <div class="flex flex-wrap gap-2">
                            <a
                                href="{{ $booking->bookingOrder ? route('order.public', $booking->bookingOrder->receipt_token) : route('booking.public', $booking->receipt_token) }}"
                                target="_blank"
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-accent-400 hover:text-accent-700 dark:border-ink-700 dark:text-ink-300 dark:hover:text-accent-400"
                            >
                                <i class="ph ph-arrow-square-out text-base"></i>
                                Open customer view
                            </a>
                            @if ((auth()->user()->isAdmin() || auth()->user()->isStaff()) && $booking->bookingOrder && $booking->bookingOrder->bookings->contains($isReschedulable))
                                <a
                                    href="{{ route('admin.bookings.reschedule.select', $booking->bookingOrder) }}"
                                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-accent-400 hover:text-accent-700 dark:border-ink-700 dark:text-ink-300 dark:hover:text-accent-400"
                                >
                                    <i class="ph ph-arrow-clockwise text-base"></i>
                                    Reschedule
                                </a>
                            @elseif ((auth()->user()->isAdmin() || auth()->user()->isStaff()) && ! $booking->bookingOrder && $isReschedulable($booking))
                                <a
                                    href="{{ route('admin.bookings.reschedule.edit', $booking) }}"
                                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-accent-400 hover:text-accent-700 dark:border-ink-700 dark:text-ink-300 dark:hover:text-accent-400"
                                >
                                    <i class="ph ph-arrow-clockwise text-base"></i>
                                    Reschedule
                                </a>
                            @endif
                            @if ((auth()->user()->isAdmin() || auth()->user()->isStaff()) && ! $booking->bookingOrder && $isHoldable($booking))
                                <a
                                    href="{{ route('admin.bookings.hold.edit', $booking) }}"
                                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-amber-400 hover:text-amber-700 dark:border-ink-700 dark:text-ink-300 dark:hover:text-amber-400"
                                >
                                    <i class="ph ph-pause-circle text-base"></i>
                                    Hold
                                </a>
                            @elseif ((auth()->user()->isAdmin() || auth()->user()->isStaff()) && $booking->bookingOrder && $booking->bookingOrder->bookings->contains($isHoldable))
                                <a
                                    href="{{ route('admin.bookings.hold.select', $booking->bookingOrder) }}"
                                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-amber-400 hover:text-amber-700 dark:border-ink-700 dark:text-ink-300 dark:hover:text-amber-400"
                                >
                                    <i class="ph ph-pause-circle text-base"></i>
                                    Hold a session
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    </div>

</x-layouts.admin>
