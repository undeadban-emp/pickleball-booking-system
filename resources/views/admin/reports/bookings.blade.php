<x-layouts.admin :title="'Booking Reports'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
            Booking Reports
        </h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.reports.bookings.export', request()->only(['from', 'to', 'court_id'])) }}"
                class="flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-semibold text-ink-700 transition-colors hover:border-accent-400 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">
                <i class="ph ph-download-simple"></i>
                Export CSV
            </a>
            <a href="{{ route('admin.reports.bookings.pdf', request()->only(['from', 'to', 'court_id'])) }}" target="_blank"
                class="flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-semibold text-ink-700 transition-colors hover:border-accent-400 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">
                <i class="ph ph-printer"></i>
                Print schedule (PDF)
            </a>
        </div>
    </div>

    <form method="GET" class="mt-5 flex flex-wrap items-end gap-3 rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">From</label>
            {{-- No `max` here on purpose: unlike the analytics cards below (which are
                only meaningful for past dates), the PDF print button reuses this same
                range to print a front-desk day-sheet, which needs to reach today's and
                future bookings too. --}}
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
        <button type="submit" class="rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
            Apply
        </button>
    </form>

    {{-- Summary cards --}}
    @php
        $__total = $statusBreakdown->sum('count');
        $__cancelled = $statusBreakdown->firstWhere('status', 'cancelled')->count ?? 0;
    @endphp
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">Total bookings</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $__total }}</p>
        </div>
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">Cancellation rate</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $__total > 0 ? round(($__cancelled / $__total) * 100) : 0 }}%</p>
        </div>
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">No-shows</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $noShowCount }}</p>
        </div>
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm text-ink-500 dark:text-ink-400">Rebooked (rain/reschedule)</p>
            <p class="mt-1 font-display text-3xl font-semibold text-ink-950 dark:text-white">{{ $rebookImpact['count'] }}</p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">₱{{ number_format($rebookImpact['total'], 2) }} carried forward</p>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Status breakdown --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">By status</p>
            <div class="mt-3 space-y-2">
                @forelse ($statusBreakdown as $row)
                    <div class="flex items-center justify-between text-sm">
                        <span class="capitalize text-ink-700 dark:text-ink-300">{{ str_replace('_', ' ', $row->status) }}</span>
                        <span class="font-medium text-ink-900 dark:text-ink-100">{{ $row->count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-ink-500 dark:text-ink-400">No data.</p>
                @endforelse
            </div>
        </div>

        {{-- Court utilization --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Court utilization</p>
            <div class="mt-3 space-y-3">
                @foreach ($utilization as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-ink-700 dark:text-ink-300">{{ $row['name'] }}</span>
                            <span class="text-ink-500 dark:text-ink-400">{{ $row['booked'] }}/{{ $row['total'] }} slots — <span class="font-medium text-ink-900 dark:text-ink-100">{{ $row['rate'] }}%</span></span>
                        </div>
                        <div class="mt-1 h-2 rounded bg-ink-100 dark:bg-ink-800">
                            <div class="h-2 rounded bg-accent-500" style="width: {{ max(2, $row['rate']) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Peak hours --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Peak hours (paid bookings)</p>
            @php $__maxHour = $peakHours->max('count') ?: 1; @endphp
            <div class="mt-3 space-y-1.5">
                @forelse ($peakHours as $row)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-16 shrink-0 text-xs text-ink-500 dark:text-ink-400">{{ sprintf('%02d:00', $row->hour) }}</span>
                        <div class="h-4 flex-1 rounded bg-ink-100 dark:bg-ink-800">
                            <div class="h-4 rounded bg-accent-500" style="width: {{ max(2, ($row->count / $__maxHour) * 100) }}%"></div>
                        </div>
                        <span class="w-10 shrink-0 text-right text-xs text-ink-500 dark:text-ink-400">{{ $row->count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-ink-500 dark:text-ink-400">No data.</p>
                @endforelse
            </div>
        </div>

        {{-- Cancellation reasons --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Cancellation reasons</p>
            <div class="mt-3 space-y-2">
                @forelse ($cancellationReasons as $row)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-700 dark:text-ink-300">{{ $row->reason }}</span>
                        <span class="font-medium text-ink-900 dark:text-ink-100">{{ $row->count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-ink-500 dark:text-ink-400">No cancellations in this range.</p>
                @endforelse
            </div>
        </div>

        {{-- Maintenance snapshot --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Courts under maintenance (right now)</p>
            <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Live snapshot, not a range total — the system doesn't log maintenance start/end times yet, so historical court-hours lost can't be computed.</p>
            <div class="mt-3 space-y-2">
                @forelse ($maintenance as $court)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-700 dark:text-ink-300">{{ $court->name }}</span>
                        <span class="text-right text-xs text-ink-500 dark:text-ink-400">
                            {{ $court->maintenance_reason ?? 'No reason given' }}
                            @if ($court->maintenance_until)
                                — until {{ $court->maintenance_until->format('M j, g:ia') }}
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-ink-500 dark:text-ink-400">No courts currently under maintenance.</p>
                @endforelse
            </div>
        </div>

        {{-- Staff activity --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Staff activity</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs font-medium tracking-wide text-ink-500 uppercase dark:text-ink-400">
                        <tr>
                            <th class="py-1.5 pr-3">Staff</th>
                            <th class="py-1.5 pr-3">Approvals</th>
                            <th class="py-1.5 pr-3">Rejections</th>
                            <th class="py-1.5 pr-3">Check-ins</th>
                            <th class="py-1.5">Holds</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @forelse ($staffActivity as $name => $data)
                            <tr>
                                <td class="py-1.5 pr-3 text-ink-800 dark:text-ink-200">{{ $name }}</td>
                                <td class="py-1.5 pr-3 text-ink-600 dark:text-ink-400">{{ $data['approvals'] }}</td>
                                <td class="py-1.5 pr-3 text-ink-600 dark:text-ink-400">{{ $data['rejections'] }}</td>
                                <td class="py-1.5 pr-3 text-ink-600 dark:text-ink-400">{{ $data['checkins'] }}</td>
                                <td class="py-1.5 text-ink-600 dark:text-ink-400">{{ $data['holds'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-1.5 text-ink-500 dark:text-ink-400">No staff activity in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Match stats --}}
        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Match/game volume</p>
            <div class="mt-3 space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-ink-700 dark:text-ink-300">Total matches</span>
                    <span class="font-medium text-ink-900 dark:text-ink-100">{{ $matchStats['total'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-ink-700 dark:text-ink-300">Avg. games per match</span>
                    <span class="font-medium text-ink-900 dark:text-ink-100">{{ $matchStats['avgGamesPerMatch'] }}</span>
                </div>
                @foreach ($matchStats['byScoringType'] as $type => $count)
                    <div class="flex items-center justify-between">
                        <span class="capitalize text-ink-700 dark:text-ink-300">{{ $type }} scoring</span>
                        <span class="font-medium text-ink-900 dark:text-ink-100">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Detail list --}}
    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-ink-950 dark:text-white">Bookings in range</h2>
    </div>

    <div class="mt-4 overflow-x-auto rounded-2xl border border-ink-200 dark:border-ink-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-ink-100 text-xs font-medium tracking-wide text-ink-500 uppercase dark:bg-ink-800 dark:text-ink-400">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Court</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100 bg-white dark:divide-ink-800 dark:bg-ink-900">
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-ink-600 dark:text-ink-400">{{ $booking->booking_code }}</td>
                        <td class="px-4 py-3 text-ink-800 dark:text-ink-200">{{ $booking->contactName() }}</td>
                        <td class="px-4 py-3 text-ink-600 dark:text-ink-400">{{ $booking->court->name }}</td>
                        <td class="px-4 py-3 capitalize text-ink-600 dark:text-ink-400">{{ str_replace('_', ' ', $booking->status) }}</td>
                        <td class="px-4 py-3 text-ink-800 dark:text-ink-200">₱{{ number_format($booking->total_price, 2) }}</td>
                        <td class="px-4 py-3 text-ink-500 dark:text-ink-400">{{ $booking->created_at->format('M j, Y g:ia') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-3 text-ink-500 dark:text-ink-400">No bookings in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bookings->links() }}</div>

</x-layouts.admin>
