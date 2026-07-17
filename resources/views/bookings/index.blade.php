<x-layouts.app :title="'My bookings'">

    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <h1 class="font-display text-3xl font-semibold tracking-tight text-ink-950 dark:text-white">
                My bookings
            </h1>
            <a href="{{ route('book.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-accent-400">
                <i class="ph ph-plus text-base"></i>
                Book a court
            </a>
        </div>

        @if ($bookings->isEmpty())
            <p class="mt-8 text-sm text-ink-500 dark:text-ink-400">You have not booked a court yet.</p>
        @else
            <div class="mt-8 space-y-3">
                @foreach ($bookings as $booking)
                    @php
                        $firstSlot = $booking->slots->sortBy('start_time')->first();
                        $badge = match ($booking->status) {
                            'confirmed' => 'bg-accent-100 text-accent-800 dark:bg-accent-900 dark:text-accent-200',
                            'pending_payment' => 'bg-ink-100 text-ink-700 dark:bg-ink-800 dark:text-ink-200',
                            'rejected' => 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
                            default => 'bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400',
                        };
                    @endphp
                    <a href="{{ route('booking.public', $booking->receipt_token) }}" class="flex items-center justify-between rounded-2xl border border-ink-100 bg-white p-4 transition-colors hover:border-accent-400 dark:border-ink-800 dark:bg-ink-900">
                        <div>
                            <p class="font-medium text-ink-950 dark:text-white">{{ $booking->court->name }}</p>
                            <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                                @if ($firstSlot)
                                    {{ \Illuminate\Support\Carbon::parse($firstSlot->slot_date)->format('M j, Y') }}, {{ \Illuminate\Support\Carbon::parse($firstSlot->start_time)->format('g:i A') }}
                                @endif
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">
                            {{ str($booking->status)->replace('_', ' ')->headline() }}
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $bookings->links() }}
            </div>
        @endif
    </section>

</x-layouts.app>
