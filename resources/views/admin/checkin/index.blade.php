<x-layouts.admin :title="'Check-in'">

    <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Front-desk check-in</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Enter the code from the customer's confirmation screen.</p>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif
    @error('booking')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            {{ $message }}
        </div>
    @enderror

    <form method="GET" class="mt-6 flex max-w-lg gap-2">
        <input
            type="text"
            name="token"
            value="{{ request('token') }}"
            placeholder="Paste or type the check-in code"
            autofocus
            class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100"
        >
        <button type="submit" class="shrink-0 rounded-xl bg-ink-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950">
            Look up
        </button>
    </form>

    @if ($searched)
        <div class="mt-6 max-w-lg">
            @if (! $booking)
                <p class="rounded-xl border border-ink-200 bg-white p-4 text-sm text-ink-600 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-400">No booking found for that code.</p>
            @else
                <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                    <p class="font-display text-lg font-semibold text-ink-950 dark:text-white">{{ $booking->contactName() }}</p>
                    <p class="text-sm text-ink-500 dark:text-ink-400">{{ $booking->court->name }}, {{ $booking->booking_code }}</p>

                    <ul class="mt-3 space-y-1 text-sm text-ink-600 dark:text-ink-400">
                        @foreach ($booking->slots->sortBy('start_time') as $slot)
                            <li>{{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} to {{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }}</li>
                        @endforeach
                    </ul>

                    @if ($booking->status === 'confirmed' && ! $booking->checked_in_at)
                        <form method="POST" action="{{ route('admin.checkin.confirm', $booking) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="w-full rounded-full bg-accent-500 px-6 py-3 text-sm font-semibold text-ink-950 hover:bg-accent-400">
                                Confirm check-in
                            </button>
                        </form>
                    @elseif ($booking->checked_in_at)
                        <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            Already checked in at {{ $booking->checked_in_at->format('g:i A') }}.
                        </p>
                    @else
                        <p class="mt-4 rounded-xl bg-ink-100 px-4 py-3 text-sm text-ink-600 dark:bg-ink-800 dark:text-ink-400">
                            This booking is not confirmed yet ({{ str($booking->status)->replace('_', ' ')->headline() }}).
                        </p>
                    @endif
                </div>
            @endif
        </div>
    @endif

</x-layouts.admin>
