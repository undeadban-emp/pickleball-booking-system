@php
    $__brandSettings = \App\Models\OperatingHours::current();
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

    $navItem = function (string $routeName, string $label, string $icon, ?int $badge = null) {
        $active = request()->routeIs($routeName.'*');
        return compact('routeName', 'label', 'icon', 'active', 'badge');
    };

    $navGroup = function (string $label, string $icon, array $children) {
        $children = collect($children)->map(function ($child) {
            $child['active'] = request()->routeIs($child['routeName'].'*');
            return $child;
        });

        return [
            'label' => $label,
            'icon' => $icon,
            'children' => $children,
            'active' => $children->contains('active', true),
        ];
    };

    $items = collect([
        $navItem('admin.dashboard', 'Dashboard', 'ph-squares-four'),
        $navItem('admin.bookings.index', 'Bookings', 'ph-calendar-check', $__pendingBookingsCount),
        $navItem('admin.bookings.schedule', 'Day Schedule', 'ph-calendar-blank'),
        // Hidden for now - re-add when check-in is ready to surface again.
        // $navItem('admin.checkin.index', 'Check-in', 'ph-qr-code'),
    ]);

    if (auth()->user()->isAdmin()) {
        $items->push($navItem('admin.courts.index', 'Courts', 'ph-tennis-ball'));
        $items->push($navItem('admin.payment-methods.index', 'Payment methods', 'ph-credit-card'));
        $items->push($navItem('admin.hero-images.index', 'Hero images', 'ph-image'));
        $items->push($navItem('admin.gallery-images.index', 'Album', 'ph-images-square'));
        $items->push($navItem('admin.users.index', 'Users', 'ph-users'));
        $items->push($navGroup('Reports', 'ph-chart-bar', [
            ['routeName' => 'admin.reports.bookings', 'label' => 'Booking Reports'],
            ['routeName' => 'admin.reports.revenue', 'label' => 'Revenue & Finance Reports'],
            ['routeName' => 'admin.reports.clients', 'label' => 'Client Reports'],
        ]));
        $items->push($navGroup('Settings', 'ph-gear-six', [
            ['routeName' => 'admin.settings.edit', 'label' => 'General'],
            ['routeName' => 'admin.settings.hours', 'label' => 'Time-of-day Groups'],
            ['routeName' => 'admin.settings.rates.index', 'label' => 'Court Rates'],
            ['routeName' => 'admin.settings.location', 'label' => 'Location'],
        ]));
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

    <nav
        class="flex-1 space-y-1 px-3 py-4"
        x-data="{ pendingCount: {{ $__pendingBookingsCount }} }"
        x-init="setInterval(() => {
            fetch('{{ route('admin.bookings.pending-count') }}', { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((body) => { pendingCount = body.pending_count })
                .catch(() => {})
        }, 15000)"
    >
        @foreach ($items as $item)
            @if (isset($item['children']))
                <details @if ($item['active']) open @endif class="group">
                    <summary
                        class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {{ $item['active'] ? 'text-white' : 'text-ink-300 hover:bg-ink-900 hover:text-white' }}"
                    >
                        <i class="ph {{ $item['icon'] }} text-lg"></i>
                        <span class="flex-1">{{ $item['label'] }}</span>
                        <i class="ph ph-caret-down text-sm transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="mt-1 space-y-1 pl-8">
                        @foreach ($item['children'] as $child)
                            <a
                                href="{{ route($child['routeName']) }}"
                                class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $child['active'] ? 'bg-accent-500 text-ink-950' : 'text-ink-400 hover:bg-ink-900 hover:text-white' }}"
                            >
                                {{ $child['label'] }}
                            </a>
                        @endforeach
                    </div>
                </details>
            @else
                <a
                    href="{{ route($item['routeName']) }}"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {{ $item['active'] ? 'bg-accent-500 text-ink-950' : 'text-ink-300 hover:bg-ink-900 hover:text-white' }}"
                >
                    <i class="ph {{ $item['icon'] }} text-lg"></i>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if ($item['badge'] !== null)
                        <span
                            x-show="pendingCount > 0"
                            x-text="pendingCount"
                            class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[11px] font-semibold text-white"
                        ></span>
                    @endif
                </a>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-ink-800 p-3">
        <a href="{{ url('/') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-400 transition-colors hover:bg-ink-900 hover:text-white">
            <i class="ph ph-arrow-square-out text-lg"></i>
            View site
        </a>
    </div>
</aside>
