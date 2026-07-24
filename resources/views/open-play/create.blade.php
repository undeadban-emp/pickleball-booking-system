<x-layouts.app :title="'Open a room'" :hide-footer="true">

    <section class="mx-auto max-w-2xl px-4 py-14 sm:px-6 lg:px-8">
        <a href="{{ route('open-play.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition-colors hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left"></i>
            Open Play
        </a>

        <h1 class="mt-4 font-display text-3xl font-semibold tracking-tight text-ink-950 dark:text-white">
            Open a room
        </h1>
        <p class="mt-2 text-sm text-ink-500 dark:text-ink-400">
            Pick from your own confirmed bookings. All selected courts must share the same date and start time.
        </p>

        @if ($bookings->isEmpty())
            <div class="mt-8 flex flex-col items-center rounded-2xl border border-dashed border-ink-200 px-6 py-12 text-center dark:border-ink-800">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-400 dark:bg-ink-800 dark:text-ink-500">
                    <i class="ph ph-calendar-x text-2xl"></i>
                </span>
                <p class="mt-4 text-sm text-ink-600 dark:text-ink-400">
                    You don't have any confirmed bookings available to open.
                </p>
                <a href="{{ route('book.index') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-4 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400">
                    Book a court
                    <i class="ph ph-arrow-right text-base"></i>
                </a>
            </div>
        @else
            <form method="POST" action="{{ route('open-play.store') }}" class="mt-8 space-y-6">
                @csrf

                <div>
                    <label for="title" class="block text-sm font-medium text-ink-800 dark:text-ink-200">Room title</label>
                    <input
                        id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="150"
                        placeholder="e.g. Friday Night Open Play"
                        class="mt-1.5 w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                    >
                    @error('title') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-ink-800 dark:text-ink-200">Which of your bookings?</label>
                    <div class="mt-1.5 space-y-2">
                        @foreach ($bookings as $booking)
                            @php $firstSlot = $booking->slots->sortBy('start_time')->first(); @endphp
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-200 p-3.5 transition-colors has-checked:border-accent-400 has-checked:bg-accent-50 dark:border-ink-700 dark:has-checked:border-accent-700 dark:has-checked:bg-accent-950">
                                <input type="checkbox" name="booking_ids[]" value="{{ $booking->id }}" class="h-4 w-4 rounded text-accent-600 focus:ring-accent-400">
                                <span class="flex items-center gap-2 text-sm text-ink-900 dark:text-ink-100">
                                    <i class="ph ph-map-pin text-ink-400"></i>
                                    <span class="font-medium">{{ $booking->court->name }}</span>
                                    @if ($firstSlot)
                                        <span class="text-ink-400">&middot;</span>
                                        <span class="text-ink-600 dark:text-ink-400">
                                            {{ \Illuminate\Support\Carbon::parse($firstSlot->slot_date)->format('M j, Y') }},
                                            {{ \Illuminate\Support\Carbon::parse($firstSlot->start_time)->format('g:i A') }}
                                        </span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('booking_ids') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="max_players" class="block text-sm font-medium text-ink-800 dark:text-ink-200">Max players</label>
                    <input
                        id="max_players" type="number" name="max_players" value="{{ old('max_players', 8) }}" min="4" max="64" required
                        class="mt-1.5 w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                    >
                    @error('max_players') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="skill_level" class="block text-sm font-medium text-ink-800 dark:text-ink-200">Skill level</label>
                    <select
                        id="skill_level" name="skill_level"
                        class="mt-1.5 w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                    >
                        <option value="any">Any</option>
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>

                <div>
                    <label for="visibility" class="block text-sm font-medium text-ink-800 dark:text-ink-200">Visibility</label>
                    <select
                        id="visibility" name="visibility" x-data x-on:change="$refs.joinCode.classList.toggle('hidden', $event.target.value !== 'private')"
                        class="mt-1.5 w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                    >
                        <option value="public">Public</option>
                        <option value="private">Private (join code)</option>
                    </select>
                    <div x-ref="joinCode" class="mt-2 hidden">
                        <input
                            type="text" name="join_code" placeholder="Join code (optional, auto-generated if blank)" maxlength="20"
                            class="w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                        >
                    </div>
                </div>

                <button type="submit" class="w-full rounded-full bg-accent-500 px-4 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400">
                    Create room
                </button>
            </form>
        @endif
    </section>

</x-layouts.app>
