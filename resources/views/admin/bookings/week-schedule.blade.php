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

    @if ($times->isEmpty())
        <div class="mt-4 rounded-2xl border border-ink-200 bg-white dark:border-ink-800 dark:bg-ink-900">
            <p class="p-5 text-sm text-ink-500 dark:text-ink-400">No time slots generated for this court in this week yet.</p>
        </div>
    @else
        {{-- Mobile: one day at a time, picked from a day strip, instead of
             a 7-column grid that would need constant horizontal scrolling
             to read on a phone. --}}
        <div
            class="mt-4 md:hidden"
            x-data="{
                days: [{{ $days->map(fn ($d) => "'".$d->toDateString()."'")->implode(',') }}],
                activeDay: '{{ $days->first(fn ($d) => $d->toDateString() === $__todayStr)?->toDateString() ?? $days->first()->toDateString() }}',
                get activeIndex() { return this.days.indexOf(this.activeDay); },
                goTo(index) {
                    if (index < 0 || index >= this.days.length) return;
                    this.activeDay = this.days[index];
                    this.$nextTick(() => document.getElementById('day-' + this.activeDay)?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' }));
                },
            }"
        >
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    @click="goTo(activeIndex - 1)"
                    :disabled="activeIndex === 0"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-ink-200 text-ink-600 transition-colors hover:border-accent-400 hover:text-ink-950 disabled:pointer-events-none disabled:opacity-30 dark:border-ink-700 dark:text-ink-300"
                    aria-label="Previous day"
                >
                    <i class="ph ph-caret-left"></i>
                </button>

                <div class="flex flex-1 gap-2 overflow-x-auto pb-1">
                    @foreach ($days as $day)
                        @php $__dayStr = $day->toDateString(); @endphp
                        <button
                            type="button"
                            id="day-{{ $__dayStr }}"
                            @click="activeDay = '{{ $__dayStr }}'"
                            class="flex shrink-0 flex-col items-center rounded-xl border px-3 py-2 text-center transition-colors"
                            :class="activeDay === '{{ $__dayStr }}'
                                ? 'border-accent-500 bg-accent-500 text-white'
                                : '{{ $__dayStr === $__todayStr ? 'border-accent-400' : 'border-ink-200 dark:border-ink-700' }} bg-white text-ink-700 dark:bg-ink-900 dark:text-ink-300'"
                        >
                            <span class="text-[10px] font-medium tracking-wide uppercase opacity-70">{{ $day->format('D') }}</span>
                            <span class="text-sm font-semibold">{{ $day->format('M j') }}</span>
                        </button>
                    @endforeach
                </div>

                <button
                    type="button"
                    @click="goTo(activeIndex + 1)"
                    :disabled="activeIndex === days.length - 1"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-ink-200 text-ink-600 transition-colors hover:border-accent-400 hover:text-ink-950 disabled:pointer-events-none disabled:opacity-30 dark:border-ink-700 dark:text-ink-300"
                    aria-label="Next day"
                >
                    <i class="ph ph-caret-right"></i>
                </button>
            </div>

            @foreach ($days as $day)
                @php $__dayStr = $day->toDateString(); @endphp
                <div x-show="activeDay === '{{ $__dayStr }}'" x-cloak class="mt-3 space-y-2">
                    @foreach ($times as $time)
                        @php
                            $__rowEndTime = $grid[$time]->first()?->end_time;
                            $__slot = $grid[$time][$__dayStr] ?? null;
                            $__booking = $__slot?->bookings->first();
                        @endphp
                        <div class="flex items-center gap-3 rounded-xl border border-ink-100 bg-white p-3 dark:border-ink-800 dark:bg-ink-900">
                            <span class="w-24 shrink-0 text-xs font-bold text-ink-700 dark:text-ink-300">
                                {{ \Illuminate\Support\Carbon::parse($time)->format('g:i A') }}@if ($__rowEndTime)<br>– {{ \Illuminate\Support\Carbon::parse($__rowEndTime)->format('g:i A') }}@endif
                            </span>
                            <div class="min-w-0 flex-1">
                                @if (! $__slot)
                                    <span class="text-xs text-ink-300 dark:text-ink-700">—</span>
                                @elseif ($__booking)
                                    <a href="{{ route('admin.bookings.schedule', ['date' => $__dayStr]) }}"
                                        class="block truncate rounded-lg px-2.5 py-1.5 text-xs font-medium transition-opacity hover:opacity-80 {{ $__cellBadge($__booking) }}">
                                        <span class="block truncate font-semibold">{{ $__booking->contactName() }}</span>
                                        <span class="block truncate opacity-80">{{ $__cellLabel($__booking) }}</span>
                                    </a>
                                @else
                                    <span class="block rounded-lg border border-dashed border-ink-200 px-2.5 py-1.5 text-center text-xs text-ink-400 dark:border-ink-700 dark:text-ink-600">
                                        Open
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- Desktop/tablet: full 7-day grid --}}
        <div class="mt-4 hidden overflow-x-auto rounded-2xl border border-ink-200 bg-white md:block dark:border-ink-800 dark:bg-ink-900">
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
        </div>
    @endif

</x-layouts.admin>
