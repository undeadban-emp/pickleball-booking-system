<x-layouts.admin :title="'Time-of-day Groups'">

    <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Time-of-day Groups</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
        Controls booking hours. Morning's start is also when the venue opens, and Evening's end is when it closes — it can be past midnight (e.g. 2am) if you stay open overnight. Whatever you set here is shown exactly as-is, nothing is auto-calculated.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @php
        $periods = [
            ['key' => 'morning', 'label' => 'Morning'],
            ['key' => 'afternoon', 'label' => 'Afternoon'],
            ['key' => 'evening', 'label' => 'Evening'],
        ];

        $step = $settings->slot_length_minutes;
        $timeOptions = collect(range(0, intdiv(1440, $step) - 1))->mapWithKeys(function ($i) use ($step) {
            $minutes = $i * $step;
            $value = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $label = \Illuminate\Support\Carbon::createFromTime(0, 0)->addMinutes($minutes)->format('g:ia');

            return [$value => $label];
        });

        // A saved time might not land on the current slot-length's increments
        // (e.g. it was set to :30 before switching to 1-hour slots) - keep it
        // selectable instead of silently swapping it for the first option.
        $optionsFor = function (string $currentValue) use ($timeOptions) {
            if ($timeOptions->has($currentValue)) {
                return $timeOptions;
            }

            $label = \Illuminate\Support\Carbon::createFromFormat('H:i', $currentValue)->format('g:ia');

            return $timeOptions->put($currentValue, $label)->sortKeys();
        };
    @endphp

    <form method="POST" action="{{ route('admin.settings.hours.update') }}" class="mt-6">
        @csrf

        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach ($periods as $period)
                    @php
                        $startField = 'period_'.$period['key'].'_start';
                        $endField = 'period_'.$period['key'].'_end';
                    @endphp
                    <div class="rounded-xl border border-ink-100 p-3 dark:border-ink-800">
                        <p class="text-xs font-semibold text-ink-700 dark:text-ink-200">{{ $period['label'] }}</p>

                        <div class="mt-2 flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">{{ $period['label'] }} Start</label>
                            <select name="{{ $startField }}" required
                                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                @foreach ($optionsFor(substr($settings->$startField, 0, 5)) as $value => $label)
                                    <option value="{{ $value }}" @selected(substr($settings->$startField, 0, 5) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error($startField)
                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-2 flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">{{ $period['label'] }} End</label>
                            <select name="{{ $endField }}" required
                                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                @foreach ($optionsFor(substr($settings->$endField, 0, 5)) as $value => $label)
                                    <option value="{{ $value }}" @selected(substr($settings->$endField, 0, 5) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error($endField)
                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex flex-col gap-1.5 sm:w-48">
                <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Slot length</label>
                <select name="slot_length_minutes" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    @foreach ([30 => '30 minutes', 60 => '1 hour', 90 => '1 hour 30 minutes', 120 => '2 hours'] as $value => $label)
                        <option value="{{ $value }}" @selected($settings->slot_length_minutes == $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-2 border-t border-ink-100 pt-4 sm:grid-cols-3 dark:border-ink-800">
                @foreach ($settings->periodRanges() as $range)
                    <div class="rounded-lg bg-ink-100/60 px-3 py-2 dark:bg-ink-800/50">
                        <p class="text-xs font-semibold text-ink-700 dark:text-ink-200">{{ $range['label'] }}</p>
                        <p class="text-xs text-ink-500 dark:text-ink-400">{{ $range['from'] }} to {{ $range['to'] }}</p>
                    </div>
                @endforeach
            </div>

            <p class="mt-3 text-xs text-ink-400">Changing hours does not rewrite slots already generated. It only affects newly created ones.</p>
        </div>

        <button type="submit" class="mt-6 w-fit rounded-full bg-ink-950 px-6 py-3 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
            Save hours
        </button>
    </form>

</x-layouts.admin>
