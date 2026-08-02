<x-layouts.admin :title="'Week Schedule'">

    @php
        $__todayStr = \Illuminate\Support\Carbon::today()->toDateString();

        // Same color/label convention as the Day Schedule list, so a court
        // reads the same way whichever view staff happen to be looking at.
        $__cellBadge = fn ($booking) => match (true) {
            $booking->status === 'pending_payment' && $booking->hasSubmittedPayment() => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300',
            $booking->status === 'confirmed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
            $booking->status === 'pending_payment' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            $booking->status === 'completed' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
            default => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
        };

        $__cellLabel = fn ($booking) => $booking->status === 'pending_payment' && $booking->hasSubmittedPayment()
            ? 'Awaiting Approval'
            : str($booking->status)->replace('_', ' ')->headline();

        $__prevWeek = $weekStart->copy()->subWeek()->toDateString();
        $__nextWeek = $weekStart->copy()->addWeek()->toDateString();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
            Week Schedule
        </h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.bookings.week-schedule.pdf', ['date' => $weekStart->toDateString(), 'court_id' => $courtId]) }}" target="_blank"
                class="flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-semibold text-ink-700 transition-colors hover:border-accent-400 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">
                <i class="ph ph-printer"></i>
                Print report (PDF)
            </a>
            <a href="{{ route('admin.bookings.schedule') }}"
                class="flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-semibold text-ink-700 transition-colors hover:border-accent-400 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">
                <i class="ph ph-calendar-blank"></i>
                Day Schedule
            </a>
        </div>
    </div>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.bookings.week-schedule', ['date' => $__prevWeek, 'court_id' => $courtId]) }}"
                class="flex h-9 w-9 items-center justify-center rounded-lg border border-ink-200 text-ink-600 transition-colors hover:border-accent-400 hover:text-ink-950 dark:border-ink-700 dark:text-ink-300">
                <i class="ph ph-caret-left"></i>
            </a>
            <span class="text-sm font-semibold text-ink-800 dark:text-ink-200">
                {{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}
            </span>
            <a href="{{ route('admin.bookings.week-schedule', ['date' => $__nextWeek, 'court_id' => $courtId]) }}"
                class="flex h-9 w-9 items-center justify-center rounded-lg border border-ink-200 text-ink-600 transition-colors hover:border-accent-400 hover:text-ink-950 dark:border-ink-700 dark:text-ink-300">
                <i class="ph ph-caret-right"></i>
            </a>
            <a href="{{ route('admin.bookings.week-schedule', ['court_id' => $courtId]) }}"
                class="ml-1 text-sm font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">
                This week
            </a>
        </div>

        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="date" value="{{ $weekStart->toDateString() }}">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Court</label>
            <select name="court_id" onchange="this.form.submit()"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                @foreach ($courts as $court)
                    <option value="{{ $court->id }}" @selected($courtId === $court->id)>{{ $court->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="mt-4 overflow-x-auto rounded-2xl border border-ink-200 bg-white dark:border-ink-800 dark:bg-ink-900">
        @if ($times->isEmpty())
            <p class="p-5 text-sm text-ink-500 dark:text-ink-400">No time slots generated for this court in this week yet.</p>
        @else
            <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-ink-100 dark:border-ink-800">
                        <th class="w-40 px-3 py-2 text-xs font-medium tracking-wide text-ink-500 uppercase dark:text-ink-400">Time</th>
                        @foreach ($days as $day)
                            <th class="px-3 py-2 text-xs font-medium tracking-wide text-ink-500 uppercase dark:text-ink-400 {{ $day->toDateString() === $__todayStr ? 'bg-accent-50 dark:bg-accent-950/30' : '' }}">
                                {{ $day->format('D') }}<br>
                                <span class="text-sm font-semibold normal-case text-ink-800 dark:text-ink-200">{{ $day->format('M j') }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @foreach ($times as $time)
                        @php
                            $__rowEndTime = $grid[$time]->first()?->end_time;
                        @endphp
                        <tr>
                            <td class="px-3 py-2 align-top text-xs font-bold whitespace-nowrap text-ink-700 dark:text-ink-300">
                                {{ \Illuminate\Support\Carbon::parse($time)->format('g:i A') }}@if ($__rowEndTime) - {{ \Illuminate\Support\Carbon::parse($__rowEndTime)->format('g:i A') }}@endif
                            </td>
                            @foreach ($days as $day)
                                @php
                                    $__dateStr = $day->toDateString();
                                    $__slot = $grid[$time][$__dateStr] ?? null;
                                    $__booking = $__slot?->bookings->first();
                                @endphp
                                <td class="px-2 py-1.5 align-top {{ $day->toDateString() === $__todayStr ? 'bg-accent-50/40 dark:bg-accent-950/10' : '' }}">
                                    @if (! $__slot)
                                        <span class="block px-2 py-2 text-center text-xs text-ink-300 dark:text-ink-700">—</span>
                                    @elseif ($__booking)
                                        <a href="{{ route('admin.bookings.schedule', ['date' => $__dateStr]) }}"
                                            class="block rounded-lg px-2 py-1.5 text-xs font-medium transition-opacity hover:opacity-80 {{ $__cellBadge($__booking) }}">
                                            <span class="block truncate font-semibold">{{ $__booking->contactName() }}</span>
                                            <span class="block truncate opacity-80">{{ $__cellLabel($__booking) }}</span>
                                        </a>
                                    @else
                                        <span class="block rounded-lg border border-dashed border-ink-200 px-2 py-1.5 text-center text-xs text-ink-400 dark:border-ink-700 dark:text-ink-600">
                                            Open
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</x-layouts.admin>
