@php
    $roleBadge = match (auth()->user()->role) {
        'admin' => 'bg-accent-100 text-accent-800 dark:bg-accent-900 dark:text-accent-200',
        default => 'bg-ink-200 text-ink-700 dark:bg-ink-800 dark:text-ink-300',
    };
    $__pendingBookingsCount = \App\Support\AdminNav::pendingBookingsCount();
    $items = \App\Support\AdminNav::items();
@endphp

<header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-4 border-b border-ink-200 bg-white px-4 sm:px-6 lg:px-8 dark:border-ink-800 dark:bg-ink-900">
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
        <div class="absolute top-full left-0 z-30 mt-2 max-h-[calc(100dvh-5rem)] w-60 overflow-y-auto rounded-xl border border-ink-100 bg-white p-2 shadow-lg dark:border-ink-800 dark:bg-ink-900">
            @foreach ($items as $item)
                @if (isset($item['children']))
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">
                            <span class="flex-1">{{ $item['label'] }}</span>
                            <i class="ph ph-caret-down text-xs transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="mt-1 space-y-1 pl-4">
                            @foreach ($item['children'] as $child)
                                <a href="{{ route($child['routeName']) }}" class="block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">
                                    {{ $child['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                @else
                    <a href="{{ route($item['routeName'], $item['query'] ?? []) }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">
                        <span>{{ $item['label'] }}</span>
                        @if ($item['routeName'] === 'admin.bookings.index' && ($item['query']['tab'] ?? null) === 'bookings')
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
