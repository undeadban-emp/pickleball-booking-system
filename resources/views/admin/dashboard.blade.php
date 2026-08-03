<x-layouts.admin :title="'Dashboard'">

    <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
        Good to see you, {{ auth()->user()->name }}.
    </h1>

    @php
        $__salesChange = $yesterdaySalesTotal > 0
            ? (($todaySalesTotal - $yesterdaySalesTotal) / $yesterdaySalesTotal) * 100
            : ($todaySalesTotal > 0 ? 100 : null);
    @endphp

    <div class="mt-6 rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
        <p class="flex items-center gap-1.5 text-sm font-semibold text-ink-950 dark:text-white">
            <i class="ph ph-calendar-blank text-base"></i>
            Sales for a date range
        </p>
        <form method="GET" class="mt-3 flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-medium text-ink-500 dark:text-ink-400">From</label>
                <input type="date" name="from" value="{{ $rangeFrom?->toDateString() }}" max="{{ today()->toDateString() }}" required
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-medium text-ink-500 dark:text-ink-400">To</label>
                <input type="date" name="to" value="{{ $rangeTo?->toDateString() }}" max="{{ today()->toDateString() }}" required
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
            </div>
            <button type="submit"
                class="rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                View sales
            </button>
        </form>

        @if ($rangeSalesTotal !== null)
            <div class="mt-4 rounded-xl border border-ink-100 bg-ink-50 px-4 py-3 dark:border-ink-800 dark:bg-ink-950">
                <p class="text-xs text-ink-500 dark:text-ink-400">{{ $rangeFrom->format('M j, Y') }} – {{ $rangeTo->format('M j, Y') }}</p>
                <p class="mt-1 font-display text-2xl font-semibold text-ink-950 dark:text-white">₱{{ number_format($rangeSalesTotal, 2) }}</p>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $rangeSalesCount }} booking{{ $rangeSalesCount === 1 ? '' : 's' }} confirmed</p>
            </div>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-ink-200 bg-ink-950 p-5 text-white dark:border-ink-800">
            <p class="flex items-center gap-1.5 text-sm text-ink-300">
                <i class="ph ph-currency-circle-dollar text-base"></i>
                Sales today
            </p>
            <p class="mt-1 font-display text-3xl font-semibold">₱{{ number_format($todaySalesTotal, 2) }}</p>
            <div class="mt-2 flex items-center gap-2 text-xs text-ink-400">
                <span>{{ $todaySalesCount }} booking{{ $todaySalesCount === 1 ? '' : 's' }} confirmed</span>
                @if ($__salesChange !== null)
                    <span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 font-semibold {{ $__salesChange >= 0 ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                        <i class="ph {{ $__salesChange >= 0 ? 'ph-trend-up' : 'ph-trend-down' }}"></i>
                        {{ number_format(abs($__salesChange), 0) }}% vs yesterday
                    </span>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="flex items-center gap-1.5 text-sm text-ink-500 dark:text-ink-400">
                <i class="ph ph-clock-counter-clockwise text-base"></i>
                Sales yesterday
            </p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">₱{{ number_format($yesterdaySalesTotal, 2) }}</p>
            <p class="mt-2 text-xs text-ink-500 dark:text-ink-400">{{ $yesterdaySalesCount }} booking{{ $yesterdaySalesCount === 1 ? '' : 's' }} confirmed</p>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('admin.bookings.index', ['tab' => 'bookings']) }}" class="rounded-2xl border border-ink-200 bg-white p-5 transition-colors hover:border-accent-400 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">Awaiting payment review</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $pendingCount }}</p>
        </a>
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">Confirmed today</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $confirmedToday }}</p>
        </div>
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">Checked in today</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $checkedInToday }}</p>
        </div>
        <a href="{{ auth()->user()->isAdmin() ? route('admin.courts.index') : '#' }}" class="rounded-2xl border border-ink-200 bg-white p-5 transition-colors hover:border-accent-400 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">Courts in maintenance</p>
            <p class="mt-1 font-display text-3xl font-semibold {{ $courtsUnderMaintenance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-ink-950 dark:text-white' }}">{{ $courtsUnderMaintenance }}</p>
        </a>
    </div>

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-ink-950 dark:text-white">Needs your review</h2>
        <a href="{{ route('admin.bookings.index') }}" class="text-sm font-medium text-accent-700 hover:text-accent-800 dark:text-accent-400">View all bookings</a>
    </div>

    @if ($pending->isEmpty())
        <p class="mt-4 text-sm text-ink-500 dark:text-ink-400">Nothing waiting on review right now.</p>
    @else
        <div class="mt-4 overflow-x-auto rounded-2xl border border-ink-200 dark:border-ink-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-ink-100 text-xs font-medium tracking-wide text-ink-500 uppercase dark:bg-ink-800 dark:text-ink-400">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Court</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Total</th>
                        @if (auth()->user()->isAdmin())
                            <th class="px-4 py-3 text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100 bg-white dark:divide-ink-800 dark:bg-ink-900">
                    @foreach ($pending as $booking)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-ink-600 dark:text-ink-400">{{ $booking->booking_code }}</td>
                            <td class="px-4 py-3 text-ink-800 dark:text-ink-200">{{ $booking->contactName() }}</td>
                            <td class="px-4 py-3 text-ink-600 dark:text-ink-400">{{ $booking->court->name }}</td>
                            <td class="px-4 py-3 text-ink-600 dark:text-ink-400">
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
                                    <span x-data="{ lightbox: false, zoomed: false }">
                                        <button type="button" @click="lightbox = true" class="ml-1 inline-flex items-center text-accent-700 hover:text-accent-800 dark:text-accent-400" title="View proof of payment">
                                            <i class="ph ph-image text-sm"></i>
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
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-800 dark:text-ink-200">₱{{ number_format($booking->total_price, 2) }}</td>
                            @if (auth()->user()->isAdmin())
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.bookings.approve', $booking) }}">
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-600">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.bookings.reject', $booking) }}" onsubmit="return confirm('Reject this booking?');">
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-rose-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-600">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</x-layouts.admin>
