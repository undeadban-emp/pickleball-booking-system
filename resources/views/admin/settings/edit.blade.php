<x-layouts.admin :title="'Settings'">

    <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Settings</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
        Time-of-day groups control which hours are open for booking. Manage GCash, bank, and other payment options on the
        <a href="{{ route('admin.payment-methods.index') }}" class="font-medium text-accent-700 underline dark:text-accent-400">Payment methods</a> page.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        @csrf

        <div class="col-span-full rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Navbar logo</p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Replace the default mark shown in the navbar and admin sidebar, and control how tall it renders.</p>

            <div
                class="mt-4 flex flex-wrap items-end gap-6"
                x-data="{ height: {{ $settings->logo_height }} }"
            >
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Logo image</label>
                    <div class="flex items-center gap-3">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-ink-100 bg-ink-50 dark:border-ink-800 dark:bg-ink-950">
                            @if ($settings->logoUrl())
                                <img src="{{ $settings->logoUrl() }}" alt="Current logo" class="max-h-12 max-w-12 object-contain">
                            @else
                                <x-logo-mark class="h-8 w-8" />
                            @endif
                        </span>
                        <input
                            name="logo"
                            type="file"
                            accept="image/*"
                            class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-1 file:text-xs file:font-semibold dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100 dark:file:bg-ink-800"
                        >
                    </div>
                    @error('logo')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Logo height <span x-text="height"></span>px</label>
                    <input
                        type="range"
                        name="logo_height"
                        min="16"
                        max="120"
                        x-model.number="height"
                        class="w-48 accent-accent-500"
                    >
                    @error('logo_height')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                @if ($settings->logoUrl())
                    <button type="submit" formaction="{{ route('admin.settings.logo.remove') }}" formnovalidate class="rounded-lg border border-ink-200 px-3 py-2 text-xs font-semibold text-ink-500 hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-400">
                        Remove logo
                    </button>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-end gap-4">
                <div class="flex flex-col gap-1.5">
                    <label for="brand_text" class="text-xs font-medium text-ink-500 dark:text-ink-400">Brand text</label>
                    <input
                        id="brand_text"
                        name="brand_text"
                        type="text"
                        value="{{ old('brand_text', $settings->brand_text) }}"
                        maxlength="60"
                        required
                        class="w-56 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100"
                        placeholder="Kitchen Line"
                    >
                    @error('brand_text')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex h-[38px] w-fit cursor-pointer items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                    <input type="hidden" name="show_brand_text" value="0">
                    <input type="checkbox" name="show_brand_text" value="1" @checked($settings->show_brand_text) class="h-4 w-4 rounded border-ink-300 text-accent-600 focus:ring-accent-500">
                    Show this text beside the logo
                </label>
            </div>
        </div>

        <div class="col-span-full rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Booking widget style</p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Choose how the "Book a Court" widget appears on the home page.</p>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer flex-col gap-2 rounded-xl border p-4 transition-colors has-checked:border-accent-500 has-checked:bg-accent-50 dark:border-ink-700 dark:has-checked:bg-accent-950">
                    <span class="flex items-center gap-2">
                        <input type="radio" name="booking_widget_style" value="grid" @checked($settings->booking_widget_style === 'grid') class="text-accent-600 focus:ring-accent-500">
                        <span class="text-sm font-semibold text-ink-900 dark:text-ink-100">Grid</span>
                    </span>
                    <span class="text-xs text-ink-500 dark:text-ink-400">Courts as column headers, times grouped by morning, afternoon, and evening underneath. Best for comparing courts side by side at a glance.</span>
                </label>

                <label class="flex cursor-pointer flex-col gap-2 rounded-xl border p-4 transition-colors has-checked:border-accent-500 has-checked:bg-accent-50 dark:border-ink-700 dark:has-checked:bg-accent-950">
                    <span class="flex items-center gap-2">
                        <input type="radio" name="booking_widget_style" value="by_court" @checked($settings->booking_widget_style === 'by_court') class="text-accent-600 focus:ring-accent-500">
                        <span class="text-sm font-semibold text-ink-900 dark:text-ink-100">By court</span>
                    </span>
                    <span class="text-xs text-ink-500 dark:text-ink-400">Each court gets its own labeled row, with morning, afternoon, and evening as columns underneath it. Reads top to bottom per court.</span>
                </label>
            </div>
            @error('booking_widget_style')
                <p class="mt-2 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="col-span-full rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Time-of-day groups</p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">
                This is what controls booking hours. Morning's start time is also when the venue opens. "Closes at" is when Late evening ends, and can be past midnight (e.g. 2am) if you stay open overnight.
            </p>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Morning starts</label>
                    <input type="time" name="period_morning_start" value="{{ substr($settings->period_morning_start, 0, 5) }}" required
                        class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    @error('period_morning_start')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Afternoon starts</label>
                    <input type="time" name="period_afternoon_start" value="{{ substr($settings->period_afternoon_start, 0, 5) }}" required
                        class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    @error('period_afternoon_start')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Evening starts</label>
                    <input type="time" name="period_evening_start" value="{{ substr($settings->period_evening_start, 0, 5) }}" required
                        class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    @error('period_evening_start')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Late evening starts</label>
                    <input type="time" name="period_late_evening_start" value="{{ substr($settings->period_late_evening_start, 0, 5) }}" required
                        class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    @error('period_late_evening_start')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Closes at</label>
                    <input type="time" name="close_time" value="{{ substr($settings->close_time, 0, 5) }}" required
                        class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    @error('close_time')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-1.5 sm:w-48">
                <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Slot length</label>
                <select name="slot_length_minutes" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    @foreach ([30 => '30 minutes', 60 => '1 hour', 90 => '1 hour 30 minutes', 120 => '2 hours'] as $value => $label)
                        <option value="{{ $value }}" @selected($settings->slot_length_minutes == $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-2 border-t border-ink-100 pt-4 sm:grid-cols-4 dark:border-ink-800">
                @foreach ($settings->periodRanges() as $range)
                    <div class="rounded-lg px-3 py-2 {{ $range['has_slots'] ? 'bg-ink-100/60 dark:bg-ink-800/50' : 'bg-ink-50 dark:bg-ink-900/40' }}">
                        <p class="text-xs font-semibold {{ $range['has_slots'] ? 'text-ink-700 dark:text-ink-200' : 'text-ink-400 dark:text-ink-600' }}">{{ $range['label'] }}</p>
                        @if ($range['has_slots'])
                            <p class="text-xs text-ink-500 dark:text-ink-400">{{ $range['from'] }} to {{ $range['to'] }}</p>
                        @else
                            <p class="text-xs text-ink-400 dark:text-ink-600">Not within operating hours</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="mt-3 text-xs text-ink-400">Changing hours does not rewrite slots already generated. It only affects newly created ones.</p>
        </div>

        <button type="submit" class="col-span-full w-fit rounded-full bg-ink-950 px-6 py-3 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
            Save settings
        </button>
    </form>

</x-layouts.admin>
