@php
    $roleBadge = match (auth()->user()->role) {
        'admin' => 'bg-accent-100 text-accent-800 dark:bg-accent-900 dark:text-accent-200',
        default => 'bg-ink-200 text-ink-700 dark:bg-ink-800 dark:text-ink-300',
    };
    // Representative bookings only, so a multi-session order pending
    // review badges as 1 item to act on - not one per session, which
    // would overcount relative to what the bookings list actually shows.
    $__pendingBookingsCount = \App\Models\Booking::where('status', 'pending_payment')
        ->where(function ($q) {
            $q->whereNull('booking_order_id')
                ->orWhereIn('id', function ($sub) {
                    $sub->selectRaw('MIN(id)')->from('bookings')->whereNotNull('booking_order_id')->groupBy('booking_order_id');
                });
        })
        ->count();
@endphp

<header class="flex h-16 items-center justify-between gap-4 border-b border-ink-200 bg-white px-4 sm:px-6 lg:px-8 dark:border-ink-800 dark:bg-ink-900">
    <details
        class="relative md:hidden"
        x-data="{ pendingCount: {{ $__pendingBookingsCount }} }"
        x-init="setInterval(() => {
            fetch('{{ route('admin.bookings.pending-count') }}', { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((body) => { pendingCount = body.pending_count })
                .catch(() => {})
        }, 15000)"
    >
        <summary class="relative flex cursor-pointer list-none items-center gap-2 rounded-lg border border-ink-200 px-3 py-2 text-sm font-medium text-ink-700 dark:border-ink-700 dark:text-ink-200">
            <i class="ph ph-list text-lg"></i>
            Menu
            <span
                x-show="pendingCount > 0"
                x-text="pendingCount"
                class="absolute -right-2 -top-2 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[11px] font-semibold text-white"
            ></span>
        </summary>
        <div class="absolute top-full left-0 z-30 mt-2 w-52 rounded-xl border border-ink-100 bg-white p-2 shadow-lg dark:border-ink-800 dark:bg-ink-900">
            <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">Dashboard</a>
            <a href="{{ route('admin.bookings.index') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">
                <span>Bookings</span>
                <span
                    x-show="pendingCount > 0"
                    x-text="pendingCount"
                    class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[11px] font-semibold text-white"
                ></span>
            </a>
            <a href="{{ route('admin.checkin.index') }}" class="block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">Check-in</a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.courts.index') }}" class="block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">Courts</a>
                <a href="{{ route('admin.payment-methods.index') }}" class="block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">Payment methods</a>
                <a href="{{ route('admin.settings.edit') }}" class="block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">Settings</a>
            @endif
        </div>
    </details>

    <div class="hidden md:block"></div>

    <div class="flex items-center gap-3">
        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $roleBadge }}">
            {{ ucfirst(auth()->user()->role) }}
        </span>
        <span class="text-sm font-medium text-ink-700 dark:text-ink-200">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ url('/logout') }}">
            @csrf
            <button type="submit" class="rounded-full border border-ink-200 p-2 text-ink-500 transition-colors hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400 dark:hover:text-white" aria-label="Log out">
                <i class="ph ph-sign-out text-lg"></i>
            </button>
        </form>
    </div>
</header>
