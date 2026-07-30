<x-layouts.admin :title="'Revenue &amp; Finance Reports'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
            Revenue &amp; Finance Reports
        </h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.reports.revenue.export', request()->only(['from', 'to', 'court_id', 'booking_type'])) }}"
                class="flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-semibold text-ink-700 transition-colors hover:border-accent-400 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">
                <i class="ph ph-download-simple"></i>
                Export CSV
            </a>
            <a href="{{ route('admin.reports.revenue.pdf', request()->only(['from', 'to', 'court_id', 'booking_type', 'status'])) }}" target="_blank"
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
        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Court</label>
            <select name="court_id"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <option value="">All courts</option>
                @foreach ($courts as $court)
                    <option value="{{ $court->id }}" @selected($courtId === $court->id)>{{ $court->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Booking type</label>
            <select name="booking_type"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <option value="">All types</option>
                <option value="online" @selected($bookingType === 'online')>Online</option>
                <option value="walk_in" @selected($bookingType === 'walk_in')>Walk-in</option>
            </select>
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Booking status</label>
            <select name="status"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <option value="">All statuses</option>
                <option value="confirmed" @selected($status === 'confirmed')>Confirmed</option>
                <option value="hold" @selected($status === 'hold')>Hold</option>
                <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
            Apply
        </button>
        @if ($courtId || $bookingType || $status)
            <a href="{{ route('admin.reports.revenue', request()->only(['from', 'to'])) }}"
                class="text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-ink-200">
                Clear filters
            </a>
        @endif
    </form>

    @php
        $__cardMeta = [
            'confirmed' => ['label' => 'Confirmed', 'accent' => 'text-accent-600 dark:text-accent-400'],
            'pending' => ['label' => 'Pending', 'accent' => 'text-amber-600 dark:text-amber-400'],
            'hold' => ['label' => 'Hold', 'accent' => 'text-sky-600 dark:text-sky-400'],
            'rejected' => ['label' => 'Rejected', 'accent' => 'text-rose-600 dark:text-rose-400'],
            'cancelled' => ['label' => 'Cancelled', 'accent' => 'text-ink-500 dark:text-ink-400'],
        ];
    @endphp

    {{-- Confirmed, Pending, Hold, Rejected & Cancelled - one row --}}
    <div class="col-span-12 mt-6">
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-4 rounded-2xl border border-ink-200 bg-white p-6 dark:border-ink-800 dark:bg-ink-900">
                {{-- confirm-col4 --}}
                <p class="text-sm font-semibold text-ink-500 dark:text-ink-400">{{ $__cardMeta['confirmed']['label'] }} revenue</p>
                <p class="mt-2 font-display text-3xl font-semibold {{ $__cardMeta['confirmed']['accent'] }}">
                    ₱{{ number_format($summary['confirmed']['total'], 2) }}
                </p>
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                    {{ $summary['confirmed']['count'] }} booking{{ $summary['confirmed']['count'] === 1 ? '' : 's' }}
                </p>
            </div>

            @foreach (['pending', 'hold', 'rejected', 'cancelled'] as $__key)
                {{-- pen/hold/rej/can - col2 --}}
                <div class="col-span-2 rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                    <p class="text-sm font-semibold text-ink-500 dark:text-ink-400">{{ $__cardMeta[$__key]['label'] }}</p>
                    <p class="mt-2 font-display text-2xl font-semibold {{ $__cardMeta[$__key]['accent'] }}">
                        ₱{{ number_format($summary[$__key]['total'], 2) }}
                    </p>
                    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                        {{ $summary[$__key]['count'] }} booking{{ $summary[$__key]['count'] === 1 ? '' : 's' }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Daily revenue --}}
    <div class="mt-6 rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
        <p class="text-sm font-semibold text-ink-950 dark:text-white">Daily revenue</p>
        @if ($daily->isEmpty())
            <p class="mt-3 text-sm text-ink-500 dark:text-ink-400">No bookings in this range.</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-ink-100 text-xs tracking-wide text-ink-500 uppercase dark:border-ink-800 dark:text-ink-400">
                            <th class="py-2 pr-4 font-medium">Date</th>
                            <th class="px-4 py-2 font-medium">Confirmed</th>
                            <th class="px-4 py-2 font-medium">Pending</th>
                            <th class="px-4 py-2 font-medium">Hold</th>
                            <th class="px-4 py-2 font-medium">Rejected</th>
                            <th class="px-4 py-2 font-medium">Cancelled</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @foreach ($daily as $date => $row)
                            <tr>
                                <td class="py-2.5 pr-4 font-medium whitespace-nowrap text-ink-800 dark:text-ink-200">
                                    {{ \Illuminate\Support\Carbon::parse($date)->format('M j, Y') }}
                                </td>
                                @foreach (['confirmed', 'pending', 'hold', 'rejected', 'cancelled'] as $__key)
                                    <td class="px-4 py-2.5 whitespace-nowrap text-ink-600 dark:text-ink-300">
                                        {{ $row[$__key]['count'] }} bkg — ₱{{ number_format($row[$__key]['total'], 2) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Revenue by court & by booking type - one row, each its own card --}}
    <div class="col-span-12 mt-4">
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-6 rounded-2xl border border-ink-200 bg-white p-6 dark:border-ink-800 dark:bg-ink-900">
                <p class="text-sm font-semibold text-ink-950 dark:text-white">Revenue by court</p>
                <div class="mt-3 space-y-2">
                    @forelse ($byCourt as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-ink-700 dark:text-ink-300">{{ $row['label'] }}</span>
                            <span class="text-ink-500 dark:text-ink-400">{{ $row['count'] }} bkg — <span class="font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($row['total'], 2) }}</span></span>
                        </div>
                    @empty
                        <p class="text-sm text-ink-500 dark:text-ink-400">No data.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-span-6 rounded-2xl border border-ink-200 bg-white p-6 dark:border-ink-800 dark:bg-ink-900">
                <p class="text-sm font-semibold text-ink-950 dark:text-white">Revenue by booking type</p>
                <div class="mt-3 space-y-2">
                    @forelse ($byBookingType as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-ink-700 dark:text-ink-300">{{ $row['label'] }}</span>
                            <span class="text-ink-500 dark:text-ink-400">{{ $row['count'] }} bkg — <span class="font-medium text-ink-900 dark:text-ink-100">₱{{ number_format($row['total'], 2) }}</span></span>
                        </div>
                    @empty
                        <p class="text-sm text-ink-500 dark:text-ink-400">No data.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

</x-layouts.admin>
