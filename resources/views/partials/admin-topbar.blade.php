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
        <span class="hidden rounded-full px-2.5 py-1 text-xs font-semibold sm:inline {{ $roleBadge }}">
            {{ ucfirst(auth()->user()->role) }}
        </span>

        {{-- Account menu: same pattern as the client-facing nav --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
            <button
                type="button"
                @click="open = !open"
                class="flex items-center gap-2 rounded-full border border-ink-200 p-1.5 text-sm font-medium text-ink-700 transition-colors hover:border-ink-300 md:pr-3 dark:border-ink-700 dark:text-ink-300 dark:hover:border-ink-600"
                aria-label="Account menu"
            >
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink-950 text-xs font-semibold text-white dark:bg-accent-500 dark:text-ink-950">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="hidden md:inline">{{ auth()->user()->name }}</span>
                <i class="ph ph-caret-down text-xs transition-transform" :class="open && 'rotate-180'"></i>
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition
                class="absolute top-full right-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-ink-100 bg-white py-1.5 shadow-xl dark:border-ink-800 dark:bg-ink-900"
            >
                <a href="{{ route('admin.settings.account') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800">
                    <i class="ph ph-user-circle text-base"></i> Account
                </a>

                <div class="my-1 border-t border-ink-100 dark:border-ink-800"></div>

                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950">
                        <i class="ph ph-sign-out text-base"></i> Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
