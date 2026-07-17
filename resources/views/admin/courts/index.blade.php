<x-layouts.admin :title="'Courts'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Courts</h1>
        <details class="group">
            <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                <i class="ph ph-plus text-base"></i>
                Add court
            </summary>
            <form method="POST" action="{{ route('admin.courts.store') }}" class="mt-4 grid grid-cols-1 gap-3 rounded-2xl border border-ink-200 bg-white p-4 sm:grid-cols-4 dark:border-ink-800 dark:bg-ink-900">
                @csrf
                <input name="name" type="text" placeholder="Court name" required class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input name="location" type="text" placeholder="Location (optional)" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input name="default_price" type="number" step="0.01" min="0" placeholder="Price / hour" required class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <button type="submit" class="rounded-lg bg-accent-500 px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-accent-400">Create</button>
            </form>
        </details>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 space-y-4">
        @foreach ($courts as $court)
            <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-display text-lg font-semibold text-ink-950 dark:text-white">{{ $court->name }}</p>
                            @if ($court->status === 'maintenance')
                                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Maintenance</span>
                            @elseif (! $court->is_active)
                                <span class="rounded-full bg-ink-200 px-2.5 py-0.5 text-xs font-semibold text-ink-600 dark:bg-ink-800 dark:text-ink-400">Inactive</span>
                            @else
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Active</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $court->location ?? 'No location set' }} · ₱{{ number_format($court->default_price, 2) }}/hr</p>
                        @if ($court->pending_bookings_count > 0)
                            <p class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-400">{{ $court->pending_bookings_count }} pending payment{{ $court->pending_bookings_count > 1 ? 's' : '' }}</p>
                        @endif
                        @if ($court->status === 'maintenance' && $court->maintenance_reason)
                            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $court->maintenance_reason }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <details class="group">
                            <summary class="cursor-pointer list-none rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-700 hover:border-ink-400 dark:border-ink-700 dark:text-ink-300">Edit</summary>
                            <form method="POST" action="{{ route('admin.courts.update', $court) }}" class="mt-3 grid grid-cols-1 gap-2 rounded-xl border border-ink-100 p-3 sm:grid-cols-2 dark:border-ink-800">
                                @csrf
                                @method('PUT')
                                <input name="name" type="text" value="{{ $court->name }}" required class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                <input name="location" type="text" value="{{ $court->location }}" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                <input name="default_price" type="number" step="0.01" min="0" value="{{ $court->default_price }}" required class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                <label class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                                    <input type="checkbox" name="is_active" value="1" @checked($court->is_active) class="rounded border-ink-300 text-accent-600 focus:ring-accent-500">
                                    Visible to customers
                                </label>
                                <button type="submit" class="col-span-full rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950">Save</button>
                            </form>
                        </details>

                        <form method="POST" action="{{ route('admin.courts.toggle-active', $court) }}">
                            @csrf
                            @if ($court->is_active)
                                <button type="submit" class="rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-700 hover:border-ink-400 dark:border-ink-700 dark:text-ink-300">Deactivate</button>
                            @else
                                <button type="submit" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950">Activate</button>
                            @endif
                        </form>

                        @if ($court->status === 'maintenance')
                            <form method="POST" action="{{ route('admin.courts.maintenance', $court) }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950">Bring back online</button>
                            </form>
                        @else
                            <details class="group">
                                <summary class="cursor-pointer list-none rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-950">Set maintenance</summary>
                                <form method="POST" action="{{ route('admin.courts.maintenance', $court) }}" class="mt-3 grid grid-cols-1 gap-2 rounded-xl border border-ink-100 p-3 dark:border-ink-800">
                                    @csrf
                                    <input name="maintenance_reason" type="text" placeholder="Reason (optional)" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                    <input name="maintenance_until" type="date" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                    <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">Confirm maintenance</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</x-layouts.admin>
