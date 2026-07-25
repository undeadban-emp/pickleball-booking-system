<x-layouts.admin :title="'Client Reports'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
            Client Reports
        </h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.reports.clients.export', request()->only(['from', 'to'])) }}"
                class="flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-semibold text-ink-700 transition-colors hover:border-accent-400 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">
                <i class="ph ph-download-simple"></i>
                Export CSV
            </a>
            <a href="{{ route('admin.reports.clients.pdf', request()->only(['from', 'to'])) }}" target="_blank"
                class="flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-semibold text-ink-700 transition-colors hover:border-accent-400 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">
                <i class="ph ph-printer"></i>
                Print report (PDF)
            </a>
        </div>
    </div>

    <form method="GET" class="mt-5 flex flex-wrap items-end gap-3 rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">From</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">To</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
        </div>
        <button type="submit" class="rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
            Apply
        </button>
    </form>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">New customers</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $newVsReturning['new'] }}</p>
        </div>
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">Returning customers</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $newVsReturning['returning'] }}</p>
        </div>
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">Registered bookings</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $guestVsRegistered['registered']['count'] }}</p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">₱{{ number_format($guestVsRegistered['registered']['total'], 2) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">Guest bookings</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $guestVsRegistered['guest']['count'] }}</p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">₱{{ number_format($guestVsRegistered['guest']['total'], 2) }}</p>
        </div>
    </div>

    <div class="mt-4 rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
        <p class="text-sm font-semibold text-ink-950 dark:text-white">Top customers by spend</p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs font-medium tracking-wide text-ink-500 uppercase dark:text-ink-400">
                    <tr>
                        <th class="py-1.5 pr-3">Customer</th>
                        <th class="py-1.5 pr-3">Bookings</th>
                        <th class="py-1.5">Total spend</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse ($topCustomers as $row)
                        <tr>
                            <td class="py-1.5 pr-3 text-ink-800 dark:text-ink-200">{{ $row->name }}</td>
                            <td class="py-1.5 pr-3 text-ink-600 dark:text-ink-400">{{ $row->count }}</td>
                            <td class="py-1.5 font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-1.5 text-ink-500 dark:text-ink-400">No registered-customer sales in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-layouts.admin>
