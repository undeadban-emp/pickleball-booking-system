@php
    $__brandSettings = \App\Models\OperatingHours::current();
    $__pendingBookingsCount = \App\Support\AdminNav::pendingBookingsCount();
    $items = \App\Support\AdminNav::items();
@endphp

<aside class="hidden w-60 shrink-0 flex-col border-r border-ink-800 bg-ink-950 text-ink-200 md:sticky md:top-0 md:flex md:h-dvh">
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
        class="scrollbar-ink flex-1 space-y-1 overflow-y-auto px-3 py-4"
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
                    href="{{ route($item['routeName'], $item['query'] ?? []) }}"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {{ $item['active'] ? 'bg-accent-500 text-ink-950' : 'text-ink-300 hover:bg-ink-900 hover:text-white' }}"
                >
                    <i class="ph {{ $item['icon'] }} text-lg"></i>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if ($item['routeName'] === 'admin.bookings.index' && ($item['query']['tab'] ?? null) === 'bookings' && $item['badge'] !== null)
                        {{-- Live-polled count (see the nav's x-init above) -
                        the only badge that needs to update without a reload. --}}
                        <span
                            x-show="pendingCount > 0"
                            x-text="pendingCount"
                            class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[11px] font-semibold text-white"
                        ></span>
                    @elseif ($item['badge'] !== null && $item['badge'] > 0)
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-[11px] font-semibold text-white">
                            {{ $item['badge'] }}
                        </span>
                    @endif
                </a>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-ink-800 p-3">
        <a href="{{ url('/') }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-400 transition-colors hover:bg-ink-900 hover:text-white">
            <i class="ph ph-arrow-square-out text-lg"></i>
            View site
        </a>
    </div>
</aside>
