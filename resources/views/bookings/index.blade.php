<x-layouts.app :title="'My bookings'" :hide-footer="true">

    <section
        class="mx-auto max-w-3xl px-4 py-14 sm:px-6 lg:px-8"
        x-data="bookingsList({
            ids: [{{ $bookings->pluck('id')->implode(',') }}],
            statuses: {{ \Illuminate\Support\Js::from($displayStatuses) }},
            hasReference: {{ \Illuminate\Support\Js::from($bookings->mapWithKeys(fn ($b) => [$b->id => $b->hasSubmittedPayment()])) }},
            courts: {{ \Illuminate\Support\Js::from($bookings->mapWithKeys(fn ($b) => [$b->id => $b->court->name])) }},
            pollUrl: '{{ route('bookings.poll') }}',
        })"
    >
        <div class="flex items-center justify-between">
            <h1 class="font-display text-3xl font-semibold tracking-tight text-ink-950 dark:text-white">
                My bookings
            </h1>
            <a href="{{ route('book.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-4 py-2 text-sm font-semibold text-white hover:bg-accent-400">
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
                        $isOrder = $booking->bookingOrder && $booking->bookingOrder->bookings_count > 1;
                        $firstSlot = $booking->slots->sortBy('start_time')->first();
                        $activeHold = ! $isOrder && $booking->status === 'on_hold' ? $booking->holds->first() : null;
                    @endphp
                    <a
                        href="{{ $isOrder ? route('order.public', $booking->bookingOrder->receipt_token) : route('booking.public', $booking->receipt_token) }}"
                        class="flex items-center justify-between rounded-2xl border border-ink-100 bg-white p-4 transition-colors hover:border-accent-400 dark:border-ink-800 dark:bg-ink-900"
                    >
                        <div>
                            <p class="font-medium text-ink-950 dark:text-white">{{ $booking->court->name }}</p>
                            @if ($isOrder)
                                <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                                    {{ $booking->bookingOrder->bookings_count }} sessions
                                    @php $orderFirstSlot = $booking->bookingOrder->bookings->flatMap->slots->sortBy('start_time')->first(); @endphp
                                    @if ($orderFirstSlot)
                                        · starting {{ \Illuminate\Support\Carbon::parse($orderFirstSlot->slot_date)->format('M j, Y') }}
                                    @endif
                                </p>
                            @else
                                <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                                    @if ($firstSlot)
                                        {{ \Illuminate\Support\Carbon::parse($firstSlot->slot_date)->format('M j, Y') }}, {{ \Illuminate\Support\Carbon::parse($firstSlot->start_time)->format('g:i A') }}
                                    @elseif ($activeHold)
                                        was {{ $activeHold->from_slot_date->format('M j, Y') }}, {{ \Illuminate\Support\Carbon::parse($activeHold->from_start_time)->format('g:i A') }}
                                    @endif
                                </p>
                            @endif
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="badgeClass({{ $booking->id }})" x-text="badgeLabel({{ $booking->id }})"></span>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $bookings->links() }}
            </div>
        @endif
    </section>

</x-layouts.app>
