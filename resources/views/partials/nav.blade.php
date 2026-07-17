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
                <span class="font-display text-lg font-semibold tracking-tight">{{ $__brandSettings->brand_text }}</span>
            @endif
        </a>

        <div class="flex items-center gap-5">
            <a href="{{ url('/') }}" class="hidden text-sm font-medium text-ink-600 transition-colors hover:text-ink-950 md:inline dark:text-ink-300 dark:hover:text-white">Home</a>

            @auth
                @if (auth()->user()->isCustomer())
                    <a href="{{ route('bookings.index') }}" class="hidden text-sm font-medium text-ink-600 transition-colors hover:text-ink-950 sm:inline dark:text-ink-300 dark:hover:text-white">My bookings</a>
                @else
                    <a href="{{ url('/admin') }}" class="hidden text-sm font-medium text-ink-600 transition-colors hover:text-ink-950 sm:inline dark:text-ink-300 dark:hover:text-white">Dashboard</a>
                @endif
            @else
                <a href="{{ route('login') }}" class="hidden text-sm font-medium text-ink-600 transition-colors hover:text-ink-950 sm:inline dark:text-ink-300 dark:hover:text-white">Log in</a>
            @endauth

            @guest
                <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-4 py-2 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400">
                    Create account
                    <i class="ph ph-arrow-right text-base"></i>
                </a>
            @else
                <a href="{{ url('/').'#availability' }}" class="inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-4 py-2 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400">
                    Book a court
                    <i class="ph ph-arrow-right text-base"></i>
                </a>
            @endguest
        </div>
    </nav>
</header>
