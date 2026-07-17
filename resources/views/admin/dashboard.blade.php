<x-layouts.admin :title="'Dashboard'">

    <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
        Good to see you, {{ auth()->user()->name }}.
    </h1>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('admin.bookings.index', ['status' => 'pending_payment']) }}" class="rounded-2xl border border-ink-200 bg-white p-5 transition-colors hover:border-accent-400 dark:border-ink-800 dark:bg-ink-900">
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
                                {{ $booking->gcash_reference ?? 'Not submitted' }}
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
