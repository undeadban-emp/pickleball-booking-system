@php
    $statusBadge = fn ($status) => match ($status) {
        'confirmed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
        'pending_payment' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
        'cancelled' => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
        'completed' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        default => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
    };
@endphp

<x-layouts.admin :title="'Bookings'">

    <div
        x-data="{
            activeId: null,
            lastId: {{ $bookings->max('id') ?? 0 }},
            newCount: 0,
            poll() {
                fetch('{{ route('admin.bookings.latest') }}?last_id=' + this.lastId, { headers: { Accept: 'application/json' } })
                    .then(res => res.ok ? res.json() : null)
                    .then(payload => {
                        if (!payload || !payload.data.length) return;
                        this.newCount += payload.data.length;
                        this.lastId = payload.data[payload.data.length - 1].id;
                        if (this.activeId === null) {
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
        <span x-text="newCount"></span> new booking<span x-show="newCount > 1">s</span> came in. Tap to refresh.
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
                @foreach (['pending_payment' => 'Pending payment', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
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
                    <tr
                        @click="activeId = {{ $booking->id }}"
                        class="cursor-pointer transition-colors hover:bg-ink-50 dark:hover:bg-ink-800/50"
                    >
                        <td class="px-4 py-3 font-mono text-xs text-ink-600 dark:text-ink-400">{{ $booking->booking_code }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-ink-900 dark:text-ink-100">{{ $booking->contactName() }}</p>
                            @if ($booking->isGuestBooking())
                                <p class="text-xs text-ink-500 dark:text-ink-500">{{ $booking->guest_phone }} · Guest</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-ink-700 dark:text-ink-300">{{ $booking->court->name }}</td>
                        <td class="px-4 py-3 text-ink-700 dark:text-ink-300">
                            {{ $booking->gcash_reference ?? 'Not submitted' }}
                            @if ($booking->paymentProofUrl())
                                <i class="ph ph-image ml-1 text-sm text-accent-700 dark:text-accent-400" title="Proof of payment attached"></i>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($booking->total_price, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge($booking->status) }}">
                                {{ str($booking->status)->replace('_', ' ')->headline() }}
                            </span>
                        </td>
                        <td class="px-4 py-3" @click.stop>
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" @click="activeId = {{ $booking->id }}" class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400" title="View details">
                                    <i class="ph ph-eye text-base"></i>
                                </button>
                                @if ($booking->status === 'pending_payment' && (auth()->user()->isAdmin() || auth()->user()->isStaff()))
                                    <form method="POST" action="{{ route('admin.bookings.approve', $booking) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-600">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.bookings.reject', $booking) }}" onsubmit="return confirm('Reject this booking?');">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-rose-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-600">Reject</button>
                                    </form>
                                @elseif ($booking->status === 'confirmed' && (auth()->user()->isAdmin() || auth()->user()->isStaff()))
                                    <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?');">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-700 hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-300">Cancel</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
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
                        <span class="mt-1 inline-block rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge($booking->status) }}">
                            {{ str($booking->status)->replace('_', ' ')->headline() }}
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
                    </div>

                    <div>
                        <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Court &amp; time</p>
                        <p class="mt-1 font-medium text-ink-900 dark:text-ink-100">{{ $booking->court->name }}</p>
                        <ul class="mt-1 space-y-0.5 text-sm text-ink-600 dark:text-ink-400">
                            @foreach ($booking->slots->sortBy('start_time') as $slot)
                                <li>{{ \Illuminate\Support\Carbon::parse($slot->slot_date)->format('M j, Y') }}, {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} to {{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }}</li>
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

                    @if ($booking->rejection_reason || $booking->cancellation_reason)
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Note</p>
                            <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">{{ $booking->rejection_reason ?? $booking->cancellation_reason }}</p>
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
                                <form method="POST" action="{{ route('admin.bookings.approve', $booking) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.bookings.reject', $booking) }}" onsubmit="return confirm('Reject this booking?');">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-rose-500 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-600">Reject</button>
                                </form>
                            @elseif ($booking->status === 'confirmed')
                                <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?');">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-300">Cancel booking</button>
                                </form>
                            @endif
                        </div>
                    @endif

                    <a href="{{ route('booking.public', $booking->receipt_token) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm font-medium text-accent-700 hover:text-accent-800 lg:col-span-2 dark:text-accent-400">
                        Open customer view
                        <i class="ph ph-arrow-square-out text-base"></i>
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    </div>

</x-layouts.admin>
