@php
    $__brandSettings = \App\Models\OperatingHours::current();

    $navItem = function (string $routeName, string $label, string $icon) {
        $active = request()->routeIs($routeName.'*');
        return compact('routeName', 'label', 'icon', 'active');
    };

    $items = collect([
        $navItem('admin.dashboard', 'Dashboard', 'ph-squares-four'),
        $navItem('admin.bookings.index', 'Bookings', 'ph-calendar-check'),
        $navItem('admin.checkin.index', 'Check-in', 'ph-qr-code'),
    ]);

    if (auth()->user()->isAdmin()) {
        $items->push($navItem('admin.courts.index', 'Courts', 'ph-tennis-ball'));
        $items->push($navItem('admin.payment-methods.index', 'Payment methods', 'ph-credit-card'));
        $items->push($navItem('admin.hero-images.index', 'Hero images', 'ph-image'));
        $items->push($navItem('admin.settings.edit', 'Settings', 'ph-gear-six'));
    }
@endphp

<aside class="hidden w-60 shrink-0 flex-col border-r border-ink-800 bg-ink-950 text-ink-200 md:flex">
    <div class="flex h-16 items-center gap-2.5 border-b border-ink-800 px-5">
        @if ($__brandSettings->logoUrl())
            <img src="{{ $__brandSettings->logoUrl() }}" alt="{{ config('app.name') }}" style="height: {{ $__brandSettings->logo_height }}px" class="max-h-9 w-auto">
        @else
            <x-logo-mark class="h-7 w-7" />
        @endif
        @if ($__brandSettings->show_brand_text)
            <div>
                <p class="font-display text-sm font-semibold text-white">{{ $__brandSettings->brand_text }}</p>
                <p class="text-[11px] text-ink-500">Admin</p>
            </div>
        @endif
    </div>

    <nav class="flex-1 space-y-1 px-3 py-4">
        @foreach ($items as $item)
            <a
                href="{{ route($item['routeName']) }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {{ $item['active'] ? 'bg-accent-500 text-ink-950' : 'text-ink-300 hover:bg-ink-900 hover:text-white' }}"
            >
                <i class="ph {{ $item['icon'] }} text-lg"></i>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="border-t border-ink-800 p-3">
        <a href="{{ url('/') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-400 transition-colors hover:bg-ink-900 hover:text-white">
            <i class="ph ph-arrow-square-out text-lg"></i>
            View site
        </a>
    </div>
</aside>
