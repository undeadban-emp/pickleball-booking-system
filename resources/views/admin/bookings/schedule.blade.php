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
        default => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
    };

    $statusLabel = fn ($booking) => $booking->status === 'pending_payment' && $booking->hasSubmittedPayment()
        ? 'Awaiting Approval'
        : str($booking->status)->replace('_', ' ')->headline();

    $originalBookingWhen = function ($booking) {
        $slots = $booking->rebookedFrom?->slots;
        $first = $slots?->first();
        $last = $slots?->last();

        if (! $first) {
            return null;
        }

        $when = $first->slot_date->format('M j, Y').', '.Carbon::parse($first->start_time)->format('g:i A');

        return $when.' – '.Carbon::parse($last->end_time)->format('g:i A');
    };

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
                        <div class="flex w-24 shrink-0 flex-col items-start">
                            @foreach ($booking->slots as $slot)
                                <span class="font-mono text-sm font-semibold text-ink-900 dark:text-ink-100">{{ Carbon::parse($slot->start_time)->format('g:i A') }}</span>
                            @endforeach
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-ink-900 dark:text-ink-100">{{ $booking->contactName() }}</p>
                            <p class="truncate text-xs text-ink-500 dark:text-ink-400">
                                {{ $booking->court->name }}
                                @if ($booking->isGuestBooking())
                                    · Guest
                                @endif
                            </p>
                            @if ($booking->rebookedFrom)
                                <p class="truncate text-xs font-medium text-accent-700 dark:text-accent-400">
                                    <i class="ph ph-arrow-clockwise text-xs"></i>
                                    Rebooked from {{ $booking->rebookedFrom->booking_code }}
                                    @if ($originalBookingWhen($booking))
                                        (originally {{ $originalBookingWhen($booking) }})
                                    @endif
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

                        <button
                            type="button"
                            @click.stop="activeId = {{ $booking->id }}"
                            class="shrink-0 rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400"
                            title="View booking info"
                        >
                            <i class="ph ph-eye text-base"></i>
                        </button>
                    </li>
                @empty
                    <li class="rounded-xl border border-dashed border-ink-200 p-6 text-center text-sm text-ink-500 dark:border-ink-800 dark:text-ink-400">
                        No bookings on this day.
                    </li>
                @endforelse
            </ul>
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
                        @if ($booking->rebookedFrom)
                            <p class="mt-1 text-xs font-medium text-accent-700 dark:text-accent-400">
                                <i class="ph ph-arrow-clockwise"></i>
                                Rebooked from {{ $booking->rebookedFrom->booking_code }}
                                @if ($originalBookingWhen($booking))
                                    (originally {{ $originalBookingWhen($booking) }})
                                @endif
                                — no new payment taken
                            </p>
                        @endif
                    </div>

                    <div>
                        <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Court &amp; time</p>
                        <p class="mt-1 font-medium text-ink-900 dark:text-ink-100">{{ $booking->court->name }}</p>
                        <ul class="mt-1 space-y-0.5 text-sm text-ink-600 dark:text-ink-400">
                            @foreach ($booking->slots->sortBy('start_time') as $slot)
                                <li>{{ Carbon::parse($slot->slot_date)->format('M j, Y') }}, {{ Carbon::parse($slot->start_time)->format('g:i A') }} to {{ Carbon::parse($slot->end_time)->format('g:i A') }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-2 font-display text-xl font-semibold text-ink-950 dark:text-white">₱{{ number_format($booking->total_price, 2) }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Payment</p>
                        @if ($booking->gcash_reference)
                            <p class="mt-1 font-mono text-sm text-ink-900 dark:text-ink-100">{{ $booking->gcash_reference }}</p>
                            <p class="text-xs text-ink-500 dark:text-ink-400">Submitted {{ $booking->gcash_submitted_at?->format('M j, g:i A') }}</p>
                        @else
                            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">No reference submitted yet.</p>
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

                    @if ($booking->statusLogs->isNotEmpty())
                        <div class="lg:col-span-2">
                            <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">History</p>
                            <ul class="mt-2 space-y-2 border-l border-ink-100 pl-3 dark:border-ink-800">
                                @foreach ($booking->statusLogs->sortByDesc('created_at') as $log)
                                    <li class="text-xs text-ink-500 dark:text-ink-400">
                                        <span class="font-medium text-ink-700 dark:text-ink-200">{{ str($log->to_status)->replace('_', ' ')->headline() }}</span>
                                        {{ $log->created_at->format('M j, g:i A') }}
                                        @if ($log->changedBy)
                                            by {{ $log->changedBy->name }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                        <div class="flex flex-wrap gap-2 border-t border-ink-100 pt-4 lg:col-span-2 dark:border-ink-800">
                            @if ($booking->status === 'pending_payment')
                                <form
                                    method="POST"
                                    action="{{ route('admin.bookings.approve', $booking) }}"
                                    onsubmit="return confirmSubmit(this, { title: 'Approve this booking?', text: 'The customer will be notified that their booking is confirmed.', icon: 'question', confirmButtonText: 'Approve', confirmButtonColor: '#10b981' });"
                                >
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Approve</button>
                                </form>
                                <form
                                    method="POST"
                                    action="{{ route('admin.bookings.reject', $booking) }}"
                                    onsubmit="return confirmSubmit(this, { title: 'Reject this booking?', text: 'The customer will be notified that their payment was not confirmed.', icon: 'warning', confirmButtonText: 'Reject', confirmButtonColor: '#e11d48' });"
                                >
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-rose-500 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-600">Reject</button>
                                </form>
                            @elseif ($booking->status === 'confirmed')
                                <form
                                    method="POST"
                                    action="{{ route('admin.bookings.cancel', $booking) }}"
                                    onsubmit="return confirmSubmit(this, { title: 'Cancel this booking?', text: 'This will free up the slot and notify the customer.', icon: 'warning', confirmButtonText: 'Cancel booking', confirmButtonColor: '#e11d48' });"
                                >
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-300">Cancel booking</button>
                                </form>
                            @endif
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 lg:col-span-2">
                        <a href="{{ route('booking.public', $booking->receipt_token) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm font-medium text-accent-700 hover:text-accent-800 dark:text-accent-400">
                            Open customer view
                            <i class="ph ph-arrow-square-out text-base"></i>
                        </a>
                        @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                            <a
                                href="{{ route('admin.bookings.create', ['guest_name' => $booking->contactName(), 'guest_phone' => $booking->contactPhone(), 'guest_email' => $booking->contactEmail(), 'court_id' => $booking->court_id, 'hours' => $booking->slots->count(), 'rebook_from' => $booking->id]) }}"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-600 hover:text-ink-900 dark:text-ink-400 dark:hover:text-white"
                            >
                                Rebook this customer
                                <i class="ph ph-arrow-clockwise text-base"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    </div>

</x-layouts.admin>
