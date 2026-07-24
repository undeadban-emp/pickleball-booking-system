<x-layouts.admin :title="'Hold — pick a session'">

    @php
        $isHoldable = fn ($b) => $b->status === 'confirmed' && ! $b->openPlayRoomCourt()->exists();
        $statusBadge = fn ($b) => match (true) {
            $b->status === 'confirmed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
            $b->status === 'pending_payment' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            $b->status === 'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
            $b->status === 'cancelled' => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
            $b->status === 'completed' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
            $b->status === 'on_hold' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            default => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
        };
        $statusLabel = fn ($b) => str($b->status)->replace('_', ' ')->headline();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                <i class="ph ph-pause-circle text-xl"></i>
            </span>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Which session are you holding?</h1>
                <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                    {{ $order->contactName() }} &middot; {{ $order->bookings->count() }} session{{ $order->bookings->count() === 1 ? '' : 's' }} in this order
                </p>
            </div>
        </div>
        <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left text-base"></i>
            Back to bookings
        </a>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($order->bookings->sortBy(fn ($b) => $b->slots->first()?->slot_date) as $session)
            @php
                $holdable = $isHoldable($session);
                $first = $session->slots->sortBy('start_time')->first();
                $last = $session->slots->sortBy('start_time')->last();
                $hold = $session->status === 'on_hold' ? $session->holds->first() : null;
            @endphp

            @if ($holdable)
                <a
                    href="{{ route('admin.bookings.hold.edit', $session) }}"
                    class="group rounded-2xl border border-ink-200 bg-white p-4 transition-colors hover:border-amber-400 hover:bg-amber-50 dark:border-ink-800 dark:bg-ink-900 dark:hover:border-amber-700 dark:hover:bg-amber-950"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-xs text-ink-400">{{ $session->booking_code }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusBadge($session) }}">{{ $statusLabel($session) }}</span>
                    </div>
                    @if ($first)
                        <p class="mt-2 font-display text-base font-semibold text-ink-950 dark:text-white">
                            {{ \Illuminate\Support\Carbon::parse($first->slot_date)->format('D, M j, Y') }}
                        </p>
                        <p class="mt-0.5 flex items-center gap-1.5 text-sm text-ink-600 dark:text-ink-400">
                            <i class="ph ph-clock text-sm"></i>
                            {{ \Illuminate\Support\Carbon::parse($first->start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($last->end_time)->format('g:i A') }}
                        </p>
                    @endif
                    <p class="mt-1 text-xs text-ink-400">{{ $session->court->name ?? '' }} &middot; ₱{{ number_format($session->total_price, 2) }}</p>

                    <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-amber-700 group-hover:text-amber-800 dark:text-amber-400">
                        Hold this session
                        <i class="ph ph-arrow-right text-xs transition-transform group-hover:translate-x-0.5"></i>
                    </span>
                </a>
            @else
                @php
                    $reason = match (true) {
                        $session->status === 'on_hold' => 'Already on hold',
                        $session->status === 'cancelled' => 'Already cancelled',
                        $session->status === 'completed' => 'Already checked in',
                        default => str($session->status)->replace('_', ' ')->headline(),
                    };
                @endphp
                <div class="cursor-not-allowed rounded-2xl border border-dashed border-ink-200 bg-ink-50/60 p-4 opacity-60 dark:border-ink-800 dark:bg-ink-950/40">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-xs text-ink-400">{{ $session->booking_code }}</span>
                        <span class="rounded-full bg-ink-200 px-2 py-0.5 text-[10px] font-semibold text-ink-600 uppercase dark:bg-ink-800 dark:text-ink-400">{{ $reason }}</span>
                    </div>
                    @if ($first)
                        <p class="mt-2 font-display text-base font-semibold text-ink-950 dark:text-white">
                            {{ \Illuminate\Support\Carbon::parse($first->slot_date)->format('D, M j, Y') }}
                        </p>
                        <p class="mt-0.5 flex items-center gap-1.5 text-sm text-ink-600 dark:text-ink-400">
                            <i class="ph ph-clock text-sm"></i>
                            {{ \Illuminate\Support\Carbon::parse($first->start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($last->end_time)->format('g:i A') }}
                        </p>
                    @elseif ($hold)
                        <p class="mt-2 text-sm text-ink-600 dark:text-ink-400">
                            was {{ $hold->from_slot_date->format('M j') }}, {{ \Illuminate\Support\Carbon::parse($hold->from_start_time)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($hold->from_end_time)->format('g:i A') }}
                        </p>
                    @endif
                    <p class="mt-1 text-xs text-ink-400">{{ $session->court->name ?? '' }} &middot; ₱{{ number_format($session->total_price, 2) }}</p>
                </div>
            @endif
        @endforeach
    </div>

</x-layouts.admin>
