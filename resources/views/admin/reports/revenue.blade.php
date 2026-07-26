<x-layouts.admin :title="'Revenue &amp; Finance Reports'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
            Revenue &amp; Finance Reports
        </h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.reports.revenue.export', request()->only(['from', 'to'])) }}"
                class="flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-semibold text-ink-700 transition-colors hover:border-accent-400 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">
                <i class="ph ph-download-simple"></i>
                Export CSV
            </a>
            <a href="{{ route('admin.reports.revenue.pdf', request()->only(['from', 'to'])) }}" target="_blank"
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

    {{-- Revenue trend --}}
    <div class="mt-6 rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
        <p class="text-sm font-semibold text-ink-950 dark:text-white">Revenue trend</p>
        @php $__maxTrend = $trend->max('total') ?: 1; @endphp
        @if ($trend->isEmpty())
            <p class="mt-3 text-sm text-ink-500 dark:text-ink-400">No confirmed sales in this range.</p>
        @else
            <div class="mt-4 space-y-2">
                @foreach ($trend as $day)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-24 shrink-0 text-xs text-ink-500 dark:text-ink-400">{{ \Carbon\Carbon::parse($day->d)->format('M j') }}</span>
                        <div class="h-5 flex-1 rounded bg-ink-100 dark:bg-ink-800">
                            <div class="h-5 rounded bg-accent-500" style="width: {{ max(2, ($day->total / $__maxTrend) * 100) }}%"></div>
                        </div>
                        <span class="w-28 shrink-0 text-right font-medium text-ink-800 dark:text-ink-200">₱{{ number_format($day->total, 2) }}</span>
                        <span class="w-16 shrink-0 text-right text-xs text-ink-500 dark:text-ink-400">{{ $day->count }} bkg</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- By court --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Revenue by court</p>
            <div class="mt-3 space-y-2">
                @forelse ($byCourt as $row)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-700 dark:text-ink-300">{{ $row->court_name }}</span>
                        <span class="text-ink-500 dark:text-ink-400">{{ $row->count }} bkg — <span class="font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($row->total, 2) }}</span></span>
                    </div>
                @empty
                    <p class="text-sm text-ink-500 dark:text-ink-400">No data.</p>
                @endforelse
            </div>
        </div>

        {{-- By payment method --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Revenue by payment method</p>
            <div class="mt-3 space-y-2">
                @forelse ($byPaymentMethod as $row)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-700 dark:text-ink-300">{{ $row->method_name }}</span>
                        <span class="text-ink-500 dark:text-ink-400">{{ $row->count }} bkg — <span class="font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($row->total, 2) }}</span></span>
                    </div>
                @empty
                    <p class="text-sm text-ink-500 dark:text-ink-400">No data.</p>
                @endforelse
            </div>
        </div>

        {{-- By source --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Revenue by source</p>
            <div class="mt-3 space-y-2">
                @forelse ($bySource as $row)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-700 dark:text-ink-300">{{ $row['label'] }}</span>
                        <span class="text-ink-500 dark:text-ink-400">{{ $row['count'] }} bkg — <span class="font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($row['total'], 2) }}</span></span>
                    </div>
                @empty
                    <p class="text-sm text-ink-500 dark:text-ink-400">No data.</p>
                @endforelse
            </div>
        </div>

        {{-- Outstanding / pending aging --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Outstanding payments (as of today)</p>
            <div class="mt-3 space-y-2">
                @foreach ($pendingAging as $bucket => $data)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-700 dark:text-ink-300">{{ $bucket }}</span>
                        <span class="text-ink-500 dark:text-ink-400">{{ $data['count'] }} bkg — <span class="font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($data['total'], 2) }}</span></span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Hold revenue --}}
    <div class="mt-4 rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
        <p class="text-sm font-semibold text-ink-950 dark:text-white">Hold revenue (HOLD)</p>
        <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Snapshot as of today, not the selected range - money currently paused on bookings put on hold that haven't been resolved yet.</p>
        <div class="mt-3 rounded-xl border border-ink-100 bg-ink-50 px-4 py-3 dark:border-ink-800 dark:bg-ink-950">
            <p class="text-xs text-ink-500 dark:text-ink-400">Currently on hold</p>
            <p class="mt-1 font-display text-xl font-semibold text-ink-950 dark:text-white">₱{{ number_format($holdRevenue['total'], 2) }}</p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $holdRevenue['count'] }} booking{{ $holdRevenue['count'] === 1 ? '' : 's' }}</p>
        </div>

        @if ($holdRevenue['byReason']->isNotEmpty())
            <div class="mt-4 space-y-2">
                @foreach ($holdRevenue['byReason'] as $reason => $data)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-700 dark:text-ink-300">{{ $reason }}</span>
                        <span class="text-ink-500 dark:text-ink-400">{{ $data['count'] }} bkg — <span class="font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($data['total'], 2) }}</span></span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Lost revenue --}}
    <div class="mt-4 rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
        <p class="text-sm font-semibold text-ink-950 dark:text-white">Lost revenue (rejected &amp; cancelled)</p>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-ink-100 bg-ink-50 px-4 py-3 dark:border-ink-800 dark:bg-ink-950">
                <p class="text-xs text-ink-500 dark:text-ink-400">Rejected</p>
                <p class="mt-1 font-display text-xl font-semibold text-ink-950 dark:text-white">₱{{ number_format($lost['rejectedTotal'], 2) }}</p>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $lost['rejectedCount'] }} booking{{ $lost['rejectedCount'] === 1 ? '' : 's' }}</p>
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50 px-4 py-3 dark:border-ink-800 dark:bg-ink-950">
                <p class="text-xs text-ink-500 dark:text-ink-400">Cancelled (not rebooked)</p>
                <p class="mt-1 font-display text-xl font-semibold text-ink-950 dark:text-white">₱{{ number_format($lost['cancelledTotal'], 2) }}</p>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $lost['cancelledCount'] }} booking{{ $lost['cancelledCount'] === 1 ? '' : 's' }}</p>
            </div>
        </div>

        @if ($lost['byReason']->isNotEmpty())
            <div class="mt-4 space-y-2">
                @foreach ($lost['byReason'] as $reason => $data)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-700 dark:text-ink-300">{{ $reason }}</span>
                        <span class="text-ink-500 dark:text-ink-400">{{ $data['count'] }} bkg — <span class="font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($data['total'], 2) }}</span></span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</x-layouts.admin>
