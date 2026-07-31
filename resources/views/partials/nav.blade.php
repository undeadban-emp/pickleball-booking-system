@php
    $__brandSettings = \App\Models\OperatingHours::current();
@endphp

<header class="sticky top-0 z-40 border-b border-ink-100 bg-white/85 backdrop-blur dark:border-ink-800 dark:bg-ink-950/85">
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
        <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2.5">
            @if ($__brandSettings->logoUrl())
                <img src="{{ $__brandSettings->logoUrl() }}" alt="{{ config('app.name') }}" style="height: {{ $__brandSettings->logo_height }}px" class="max-h-11 w-auto">
            @else
                <x-logo-mark />
            @endif
            @if ($__brandSettings->show_brand_text)
                <span class="text-base tracking-tight sm:text-lg" style="font-family: 'Alfa Slab One', sans-serif;">{{ $__brandSettings->brand_text }}</span>
            @endif
        </a>

        <div class="flex items-center gap-2 sm:gap-4 md:gap-5">
            <a href="{{ url('/') }}" class="hidden text-sm font-medium text-ink-600 transition-colors hover:text-ink-950 md:order-1 md:inline dark:text-ink-300 dark:hover:text-white">Home</a>
            @auth
                {{-- <a href="{{ route('open-play.index') }}" class="hidden text-sm font-medium text-ink-600 transition-colors hover:text-ink-950 md:order-2 md:inline dark:text-ink-300 dark:hover:text-white">Open Play</a> --}}
            @endauth

            @guest
                <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-3 py-1.5 text-xs font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400 sm:px-4 sm:py-2 sm:text-sm md:order-4">
                    Create account
                    <i class="ph ph-arrow-right hidden text-base sm:inline"></i>
                </a>
            @else
                {{-- Ordered after the account links below (md:order-4) so on
                     desktop it sits right beside the account menu, not before
                     "My bookings" - mobile keeps natural source order. --}}
                <a href="{{ url('/').'#availability' }}" class="inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-3 py-1.5 text-xs font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400 sm:px-4 sm:py-2 sm:text-sm md:order-4">
                    Book a court
                    <i class="ph ph-arrow-right hidden text-base sm:inline"></i>
                </a>
            @endguest

            @auth
                @if (auth()->user()->isCustomer())
                    @php
                        // Representative bookings only, so a multi-session
                        // order pending review counts as 1 - not one per
                        // session, which would overcount relative to what
                        // "My bookings" actually shows.
                        $__pendingCount = auth()->user()->bookings()
                            ->where('status', 'pending_payment')
                            ->where(function ($q) {
                                $q->whereNull('booking_order_id')
                                    ->orWhereIn('id', function ($sub) {
                                        $sub->selectRaw('MIN(id)')->from('bookings')->whereNotNull('booking_order_id')->groupBy('booking_order_id');
                                    });
                            })
                            ->count();
                    @endphp
                    <a
                        href="{{ route('bookings.index') }}"
                        class="relative hidden text-sm font-medium text-ink-600 transition-colors hover:text-ink-950 sm:inline md:order-3 dark:text-ink-300 dark:hover:text-white"
                        x-data="{ count: {{ $__pendingCount }} }"
                        x-init="setInterval(() => {
                            fetch('{{ route('bookings.poll') }}', { headers: { Accept: 'application/json' } })
                                .then((r) => r.json())
                                .then((body) => { count = body.pending_count })
                                .catch(() => {})
                        }, 15000)"
                    >
                        My bookings
                        <span
                            x-show="count > 0"
                            x-text="count"
                            class="absolute -right-3.5 -top-2 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                        ></span>
                    </a>
                @else
                    <a href="{{ url('/admin') }}" class="hidden text-sm font-medium text-ink-600 transition-colors hover:text-ink-950 sm:inline md:order-3 dark:text-ink-300 dark:hover:text-white">Dashboard</a>
                @endif

                {{-- Account menu: hamburger on mobile, name + avatar on desktop --}}
                <div class="relative md:order-5" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex items-center gap-2 rounded-full border border-ink-200 p-1.5 text-sm font-medium text-ink-700 transition-colors hover:border-ink-300 md:pr-3 dark:border-ink-700 dark:text-ink-300 dark:hover:border-ink-600"
                        aria-label="Account menu"
                    >
                        <span class="hidden h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink-950 text-xs font-semibold text-white md:flex dark:bg-accent-500 dark:text-ink-950">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <span class="hidden md:inline">{{ auth()->user()->name }}</span>
                        <i class="ph ph-list text-lg md:hidden"></i>
                        <i class="ph ph-caret-down hidden text-xs transition-transform md:inline" :class="open && 'rotate-180'"></i>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition
                        class="absolute top-full right-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-ink-100 bg-white py-1.5 shadow-xl dark:border-ink-800 dark:bg-ink-900"
                    >
                        <a href="{{ url('/') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800">
                            <i class="ph ph-house text-base"></i> Home
                        </a>

                        <a href="{{ route('open-play.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800">
                            <i class="ph ph-users-three text-base"></i> Open Play
                        </a>

                        @if (auth()->user()->isCustomer())
                            <a href="{{ route('bookings.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800">
                                <i class="ph ph-calendar-check text-base"></i> My bookings
                            </a>
                        @else
                            <a href="{{ url('/admin') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800">
                                <i class="ph ph-squares-four text-base"></i> Dashboard
                            </a>
                        @endif

                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800">
                            <i class="ph ph-user-circle text-base"></i> Edit profile
                        </a>

                        <div class="my-1 border-t border-ink-100 dark:border-ink-800"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950">
                                <i class="ph ph-sign-out text-base"></i> Log out
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="hidden text-sm font-medium text-ink-600 transition-colors hover:text-ink-950 sm:inline md:order-3 dark:text-ink-300 dark:hover:text-white">Log in</a>
            @endauth
        </div>
    </nav>
</header>
