@php
    use Illuminate\Support\Carbon;

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

    // Reschedules now update the same booking in place instead of creating
    // a replacement row - this is the most recent move, if any.
    $rescheduleNote = fn ($booking) => $booking->relationLoaded('rescheduleLogs') ? $booking->rescheduleLogs->last() : null;

    $isReschedulable = fn ($booking) => in_array($booking->status, ['pending_payment', 'confirmed'], true)
        && (! $booking->slots->max('slot_date') || Carbon::parse($booking->slots->max('slot_date'))->gte(today()))
        && ! $booking->openPlayRoomCourt()->exists();

    // Only a currently confirmed booking (not already on hold, rejected,
    // etc.) not tied to an active Open Play room can be put on hold.
    $isHoldable = fn ($booking) => $booking->status === 'confirmed' && ! $booking->openPlayRoomCourt()->exists();

    // A partial hold (or partial reschedule) splits a booking's untouched
    // hours off into a sibling booking row that keeps its original
    // date/time - this session might be that sibling, or the piece it was
    // split from might itself be on hold elsewhere.
    $splitFromNote = fn ($booking) => $booking->relationLoaded('splitFrom') ? $booking->splitFrom : null;
    $splitSiblingsNote = fn ($booking) => $booking->relationLoaded('splitSiblings') ? $booking->splitSiblings : collect();
    $splitFromHoldNote = fn ($booking) => $booking && $booking->status === 'on_hold' && $booking->relationLoaded('holds')
        ? $booking->holds->whereNull('resolved_at')->first()
        : null;

    $todayStr = Carbon::today()->toDateString();
    $selectedStr = $date->toDateString();

    $monthStart = $date->copy()->startOfMonth();
    $monthEnd = $date->copy()->endOfMonth();
    $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
    $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

    $weeks = [];
    $cursor = $gridStart->copy();
    while ($cursor->lte($gridEnd)) {
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $week[] = $cursor->copy();
            $cursor->addDay();
        }
        $weeks[] = $week;
    }
@endphp

<x-layouts.admin :title="'Day Schedule'">

    <div x-data="{ activeId: null }" @keydown.escape.window="activeId = null">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Day Schedule</h1>
        <a
            href="{{ route('admin.reports.bookings.pdf', ['from' => $selectedStr, 'to' => $selectedStr]) }}"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-4 py-2 text-sm font-semibold text-ink-700 transition-colors hover:border-ink-400 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200 dark:hover:border-ink-500"
        >
            <i class="ph ph-printer text-base"></i>
            Print this day
        </a>
    </div>

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

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[260px_1fr] lg:items-start">
        {{-- Calendar --}}
        <div class="mx-auto w-full max-w-65 rounded-2xl border border-ink-200 bg-white p-3 dark:border-ink-800 dark:bg-ink-900 lg:mx-0">
            <div class="flex items-center justify-between">
                <a
                    href="{{ route('admin.bookings.schedule', ['date' => $monthStart->copy()->subMonth()->startOfMonth()->toDateString()]) }}"
                    class="rounded-lg p-1.5 text-ink-500 hover:bg-ink-100 hover:text-ink-800 dark:text-ink-400 dark:hover:bg-ink-800 dark:hover:text-white"
                    aria-label="Previous month"
                >
                    <i class="ph ph-caret-left text-sm"></i>
                </a>
                <p class="font-display text-xs font-semibold text-ink-900 dark:text-white">{{ $date->format('F Y') }}</p>
                <a
                    href="{{ route('admin.bookings.schedule', ['date' => $monthStart->copy()->addMonth()->startOfMonth()->toDateString()]) }}"
                    class="rounded-lg p-1.5 text-ink-500 hover:bg-ink-100 hover:text-ink-800 dark:text-ink-400 dark:hover:bg-ink-800 dark:hover:text-white"
                    aria-label="Next month"
                >
                    <i class="ph ph-caret-right text-sm"></i>
                </a>
            </div>

            <div class="mt-2 grid grid-cols-7 gap-0.5 text-center text-[10px] font-medium tracking-wide text-ink-400 uppercase">
                @foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $d)
                    <div class="py-1">{{ $d }}</div>
                @endforeach
            </div>

            <div class="mt-1 grid grid-cols-7 gap-0.5">
                @foreach ($weeks as $week)
                    @foreach ($week as $day)
                        @php
                            $dayStr = $day->toDateString();
                            $inMonth = $day->month === $date->month;
                            $isToday = $dayStr === $todayStr;
                            $isSelected = $dayStr === $selectedStr;
                        @endphp
                        <a
                            href="{{ route('admin.bookings.schedule', ['date' => $dayStr]) }}"
                            class="flex aspect-square items-center justify-center rounded-md text-xs transition-colors
                                {{ $isSelected ? 'bg-accent-500 font-semibold text-ink-950' : ($isToday ? 'border border-accent-400 text-ink-900 dark:text-white' : 'text-ink-700 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800') }}
                                {{ ! $inMonth && ! $isSelected ? 'text-ink-300 dark:text-ink-700' : '' }}"
                        >
                            {{ $day->day }}
                        </a>
                    @endforeach
                @endforeach
            </div>

            <a
                href="{{ route('admin.bookings.schedule', ['date' => $todayStr]) }}"
                class="mt-4 block rounded-lg border border-ink-200 px-3 py-2 text-center text-sm font-medium text-ink-700 hover:border-ink-400 hover:text-ink-950 dark:border-ink-700 dark:text-ink-300 dark:hover:text-white"
            >
                Jump to today
            </a>
        </div>

        {{-- Selected day's bookings --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold text-ink-950 dark:text-white">{{ $date->format('l, F j, Y') }}</h2>
                <span class="rounded-full bg-ink-100 px-2.5 py-1 text-xs font-semibold text-ink-600 dark:bg-ink-800 dark:text-ink-300">
                    {{ $bookings->count() }} booking{{ $bookings->count() === 1 ? '' : 's' }}
                </span>
            </div>

            <ul class="mt-4 space-y-3">
                @forelse ($bookings as $booking)
                    <li
                        @click="activeId = {{ $booking->id }}"
                        class="flex cursor-pointer items-center gap-4 rounded-xl border border-ink-100 p-3 transition-colors hover:bg-ink-50 dark:border-ink-800 dark:hover:bg-ink-800/50"
                    >
                        <div class="flex w-36 shrink-0 flex-col items-start divide-y divide-ink-100 pr-4 dark:divide-ink-800">
                            @foreach ($booking->slots->sortBy('start_time') as $slot)
                                <span class="w-full py-1 font-mono text-sm font-semibold text-ink-900 first:pt-0 last:pb-0 dark:text-ink-100">{{ Carbon::parse($slot->start_time)->format('g:i A') }} to {{ Carbon::parse($slot->end_time)->format('g:i A') }}</span>
                            @endforeach
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-ink-900 dark:text-ink-100">{{ $booking->contactName() }}</p>
                            <p class="truncate text-xs text-ink-500 dark:text-ink-400">
                                <span class="font-mono text-ink-400">{{ $booking->booking_code }}</span>
                                &middot; {{ $booking->court->name }}
                                @if ($booking->isGuestBooking())
                                    · Guest
                                @endif
                            </p>
                            @if ($rescheduleNote($booking))
                                @php $__log = $rescheduleNote($booking); @endphp
                                <p class="truncate text-xs font-medium text-accent-700 dark:text-accent-400">
                                    <i class="ph ph-arrow-clockwise text-xs"></i>
                                    Rescheduled from {{ $__log->from_slot_date->format('M j') }}, {{ Carbon::parse($__log->from_start_time)->format('g:i A') }}–{{ Carbon::parse($__log->from_end_time)->format('g:i A') }}
                                </p>
                            @endif
                            @if ($booking->bookingOrder)
                                <p class="truncate text-xs font-medium text-sky-700 dark:text-sky-400">
                                    <i class="ph ph-shopping-cart-simple text-xs"></i>
                                    Order · {{ $booking->bookingOrder->bookings_count }} sessions
                                </p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-0.5">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge($booking) }}">
                                {{ $statusLabel($booking) }}
                            </span>
                            @if ($booking->status === 'cancelled')
                                <p class="max-w-40 text-right text-xs text-ink-500 dark:text-ink-400">{{ $booking->cancellationSummary() }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-2" @click.stop>
                            <button
                                type="button"
                                @click="activeId = {{ $booking->id }}"
                                class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400"
                                title="View booking info"
                            >
                                <i class="ph ph-eye text-base"></i>
                            </button>
                            @if ((auth()->user()->isAdmin() || auth()->user()->isStaff()) && $isReschedulable($booking))
                                <a
                                    href="{{ route('admin.bookings.reschedule.edit', $booking) }}"
                                    class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-accent-400 hover:text-accent-700 dark:border-ink-700 dark:text-ink-400"
                                    title="Reschedule this booking"
                                >
                                    <i class="ph ph-arrow-clockwise text-base"></i>
                                </a>
                            @endif
                            @if ((auth()->user()->isAdmin() || auth()->user()->isStaff()) && $isHoldable($booking))
                                <a
                                    href="{{ route('admin.bookings.hold.edit', $booking) }}"
                                    class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-amber-400 hover:text-amber-700 dark:border-ink-700 dark:text-ink-400"
                                    title="Put this booking on hold"
                                >
                                    <i class="ph ph-pause-circle text-base"></i>
                                </a>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="rounded-xl border border-dashed border-ink-200 p-6 text-center text-sm text-ink-500 dark:border-ink-800 dark:text-ink-400">
                        No bookings on this day.
                    </li>
                @endforelse
            </ul>

            @if ($rescheduledAway->isNotEmpty())
                <div class="mt-5 border-t border-ink-100 pt-4 dark:border-ink-800">
                    <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Rescheduled away from this day</p>
                    <ul class="mt-2 space-y-2">
                        @foreach ($rescheduledAway as $log)
                            <li class="flex flex-wrap items-center gap-x-2 gap-y-0.5 rounded-xl border border-dashed border-ink-200 p-3 text-sm dark:border-ink-700">
                                <span class="font-mono text-xs text-ink-500 dark:text-ink-400">{{ $log->booking->booking_code }}</span>
                                <span class="text-ink-700 dark:text-ink-300">{{ $log->booking->contactName() }}</span>
                                <span class="text-ink-400">↻ moved to</span>
                                <a href="{{ route('admin.bookings.schedule', ['date' => $log->to_slot_date->toDateString()]) }}" class="inline-flex items-center gap-1 font-medium text-accent-700 hover:text-accent-800 dark:text-accent-400">
                                    {{ $log->to_slot_date->format('M j, Y') }}, {{ \Illuminate\Support\Carbon::parse($log->to_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($log->to_end_time)->format('g:i A') }}
                                    <i class="ph ph-arrow-right text-xs"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
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

                <div class="grid grid-cols-1 gap-6 px-5 py-5 lg:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Customer</p>
                        <p class="mt-1 font-medium text-ink-900 dark:text-ink-100">{{ $booking->contactName() }}</p>
                        <p class="text-sm text-ink-500 dark:text-ink-400">
                            {{ $booking->user->phone ?? $booking->guest_phone ?? 'No phone' }}
                            @if ($booking->isGuestBooking())
                                · Guest
                            @endif
                        </p>
                        @if ($booking->user->email ?? $booking->guest_email)
                            <p class="text-sm text-ink-500 dark:text-ink-400">{{ $booking->user->email ?? $booking->guest_email }}</p>
                        @endif
                        @if ($rescheduleNote($booking))
                            @php $__log = $rescheduleNote($booking); @endphp
                            <p class="mt-1 text-xs font-medium text-accent-700 dark:text-accent-400">
                                <i class="ph ph-arrow-clockwise"></i>
                                Rescheduled from {{ $__log->from_slot_date->format('M j') }}, {{ Carbon::parse($__log->from_start_time)->format('g:i A') }}–{{ Carbon::parse($__log->from_end_time)->format('g:i A') }}
                                to {{ $__log->to_slot_date->format('M j') }}, {{ Carbon::parse($__log->to_start_time)->format('g:i A') }}–{{ Carbon::parse($__log->to_end_time)->format('g:i A') }}
                                — no new payment taken
                            </p>
                        @endif
                        @if ($booking->bookingOrder)
                            <p class="mt-1 text-xs font-medium text-sky-700 dark:text-sky-400">
                                <i class="ph ph-shopping-cart-simple"></i>
                                Part of an order — {{ $booking->bookingOrder->bookings_count }} sessions, one combined payment
                            </p>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-ink-100 p-4 dark:border-ink-800">
                        <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
                            <i class="ph ph-calendar text-sm"></i> Court &amp; time
                        </p>
                        @php
                            $firstSlot = $booking->slots->sortBy('start_time')->first();
                            $lastSlot = $booking->slots->sortBy('start_time')->last();
                        @endphp
                        <p class="mt-2 font-medium text-ink-900 dark:text-ink-100">{{ $booking->court->name }}</p>
                        @if ($firstSlot)
                            <p class="mt-1 font-display text-sm font-semibold text-ink-950 dark:text-white">
                                {{ Carbon::parse($firstSlot->slot_date)->format('D, M j, Y') }}
                            </p>
                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-ink-600 dark:text-ink-400">
                                <i class="ph ph-clock text-sm"></i>
                                {{ Carbon::parse($firstSlot->start_time)->format('g:i A') }}–{{ Carbon::parse($lastSlot->end_time)->format('g:i A') }}
                            </p>
                        @endif
                        <p class="mt-2 border-t border-ink-100 pt-2 font-display text-xl font-semibold text-ink-950 dark:border-ink-800 dark:text-white">₱{{ number_format($booking->total_price, 2) }}</p>

                        @php
                            $splitFrom = $splitFromNote($booking);
                            $splitSiblings = $splitSiblingsNote($booking);
                        @endphp
                        @if ($splitFrom)
                            @php $splitFromHold = $splitFromHoldNote($splitFrom); @endphp
                            <p class="mt-2 border-t border-ink-100 pt-2 text-xs text-ink-400 dark:border-ink-800">
                                ✂ Split off from {{ $splitFrom->booking_code }}
                                @if ($splitFromHold)
                                    (on hold: {{ $splitFromHold->from_slot_date->format('M j') }}, {{ Carbon::parse($splitFromHold->from_start_time)->format('g:i A') }}–{{ Carbon::parse($splitFromHold->from_end_time)->format('g:i A') }})
                                @endif
                                — kept at its original time
                            </p>
                        @endif
                        @if ($splitSiblings->isNotEmpty())
                            <p class="mt-2 border-t border-ink-100 pt-2 text-xs text-ink-400 dark:border-ink-800">✂ {{ $splitSiblings->count() }} hour(s) split off, kept booked — see {{ $splitSiblings->pluck('booking_code')->implode(', ') }}</p>
                        @endif
                    </div>

                    <div>
                        <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Payment</p>
                        @if ($booking->gcash_reference)
                            <p class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                                <i class="ph ph-check-circle text-sm"></i> Reference submitted
                            </p>
                            <p class="mt-2 font-mono text-sm text-ink-900 dark:text-ink-100">{{ $booking->gcash_reference }}</p>
                            <p class="text-xs text-ink-500 dark:text-ink-400">Submitted {{ $booking->gcash_submitted_at?->format('M j, g:i A') }}</p>
                        @elseif ($booking->paymentProofUrl())
                            <p class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                                <i class="ph ph-check-circle text-sm"></i> Proof of payment submitted
                            </p>
                            <p class="text-xs text-ink-500 dark:text-ink-400">Submitted {{ $booking->gcash_submitted_at?->format('M j, g:i A') }}</p>
                        @else
                            <p class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-400">
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
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Note</p>
                            <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">{{ $booking->rejection_reason ?? $booking->cancellationSummary() }}</p>
                        </div>
                    @endif

                    @php
                        $history = $booking->statusLogs
                            ->map(fn ($log) => ['at' => $log->created_at, 'changedBy' => $log->changedBy, 'label' => str($log->to_status)->replace('_', ' ')->headline()])
                            ->concat($booking->rescheduleLogs->map(fn ($log) => [
                                'at' => $log->created_at,
                                'changedBy' => $log->changedBy,
                                'label' => "Rescheduled from {$log->from_slot_date->format('M j')}, ".Carbon::parse($log->from_start_time)->format('g:i A').'–'.Carbon::parse($log->from_end_time)->format('g:i A')." to {$log->to_slot_date->format('M j')}, ".Carbon::parse($log->to_start_time)->format('g:i A').'–'.Carbon::parse($log->to_end_time)->format('g:i A'),
                            ]))
                            ->sortByDesc('at');
                    @endphp
                    @if ($history->isNotEmpty())
                        <div class="lg:col-span-2">
                            <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">History</p>
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

                {{-- Actions --}}
                <div class="sticky bottom-0 -mx-5 -mb-5 space-y-3 border-t border-ink-100 bg-white/95 px-5 py-4 backdrop-blur lg:col-span-2 dark:border-ink-800 dark:bg-ink-900/95">
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
                                    onsubmit="return confirmSubmit(this, { title: 'Reject this booking?', text: 'The customer will be notified that their payment was not confirmed.', icon: 'warning', confirmButtonText: 'Reject', confirmButtonColor: '#e11d48' });"
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
                            href="{{ route('booking.public', $booking->receipt_token) }}"
                            target="_blank"
                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-accent-400 hover:text-accent-700 dark:border-ink-700 dark:text-ink-300 dark:hover:text-accent-400"
                        >
                            <i class="ph ph-arrow-square-out text-base"></i>
                            Open customer view
                        </a>
                        @if ((auth()->user()->isAdmin() || auth()->user()->isStaff()) && $isReschedulable($booking))
                            <a
                                href="{{ route('admin.bookings.reschedule.edit', $booking) }}"
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-accent-400 hover:text-accent-700 dark:border-ink-700 dark:text-ink-300 dark:hover:text-accent-400"
                            >
                                <i class="ph ph-arrow-clockwise text-base"></i>
                                Reschedule
                            </a>
                        @endif
                        @if ((auth()->user()->isAdmin() || auth()->user()->isStaff()) && $isHoldable($booking))
                            <a
                                href="{{ route('admin.bookings.hold.edit', $booking) }}"
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-amber-400 hover:text-amber-700 dark:border-ink-700 dark:text-ink-300 dark:hover:text-amber-400"
                            >
                                <i class="ph ph-pause-circle text-base"></i>
                                Hold
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
