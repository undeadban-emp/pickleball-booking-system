@php
    $statusMeta = match ($order->status) {
        'pending_payment' => $order->gcash_reference
            ? ['label' => 'Awaiting confirmation', 'icon' => 'ph-clock-countdown', 'classes' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300']
            : ['label' => 'Awaiting payment', 'icon' => 'ph-clock-countdown', 'classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'],
        'confirmed' => ['label' => 'Confirmed', 'icon' => 'ph-check-circle', 'classes' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'],
        'rejected' => ['label' => 'Payment not confirmed', 'icon' => 'ph-x-circle', 'classes' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'ph-prohibit', 'classes' => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400'],
        default => ['label' => $order->status, 'icon' => 'ph-info', 'classes' => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400'],
    };
    $canCancel = $order->status === 'pending_payment' && ! $order->gcash_reference;
@endphp

<x-layouts.app :title="'Booking order '.$order->id" :hide-footer="true">

    <section
        class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8"
        @if ($order->status === 'pending_payment')
            x-data="{
                statusUrl: '{{ route('order.public.status', $order->receipt_token) }}',
                knownStatus: '{{ $order->status }}',
                knownHasReference: {{ $order->gcash_reference ? 'true' : 'false' }},
                poll() {
                    fetch(this.statusUrl, { headers: { Accept: 'application/json' } })
                        .then(res => res.ok ? res.json() : null)
                        .then(data => {
                            if (!data) return;
                            if (data.status !== this.knownStatus || data.has_reference !== this.knownHasReference) {
                                window.location.reload();
                            }
                        })
                        .catch(() => {});
                }
            }"
            x-init="setInterval(() => poll(), {{ $order->gcash_reference ? 15000 : 20000 }})"
        @endif
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ auth()->check() ? route('bookings.index') : url('/') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
                <i class="ph ph-arrow-left"></i>
                {{ auth()->check() ? 'My bookings' : 'Kitchen Line' }}
            </a>

            @if ($canCancel)
                <div x-data="{ open: false }">
                    <button type="button" @click="open = true" class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-700 transition-colors hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-200">
                        <i class="ph ph-x-circle text-sm"></i>
                        Cancel booking
                    </button>

                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/40 px-4">
                        <div @click.outside="open = false" class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-ink-900">
                            <p class="font-display text-lg font-semibold text-ink-950 dark:text-white">Cancel this booking?</p>
                            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">All {{ $order->bookings->count() }} sessions will be released and this can't be undone.</p>
                            <form method="POST" action="{{ route('order.public.cancel', $order->receipt_token) }}" class="mt-4 space-y-3">
                                @csrf
                                <textarea name="reason" rows="2" placeholder="Reason (optional)" class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white"></textarea>
                                <div class="flex gap-2">
                                    <button type="button" @click="open = false" class="flex-1 rounded-full border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-ink-400 dark:border-ink-700 dark:text-ink-200">
                                        Keep booking
                                    </button>
                                    <button type="submit" class="flex-1 rounded-full bg-rose-500 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-600">
                                        Yes, cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-xl border border-accent-300 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-800 dark:bg-accent-950 dark:text-accent-200">
                {{ session('status') }}
            </div>
        @endif
        @error('order')
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
                {{ $message }}
            </div>
        @enderror

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-5">
            {{-- Order summary --}}
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-ink-100 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-ink-950 dark:text-white">{{ $order->bookings->count() }} session{{ $order->bookings->count() === 1 ? '' : 's' }}</p>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $statusMeta['classes'] }}">
                            <i class="ph {{ $statusMeta['icon'] }} text-lg"></i>
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @foreach ($order->bookings as $booking)
                            @php
                                $firstSlot = $booking->slots->sortBy('start_time')->first();
                                $lastSlot = $booking->slots->sortBy('start_time')->last();
                                $dateLine = $firstSlot ? \Illuminate\Support\Carbon::parse($firstSlot->slot_date)->format('M j, Y') : '';
                                $timeLine = $firstSlot ? \Illuminate\Support\Carbon::parse($firstSlot->start_time)->format('g:i A').' to '.\Illuminate\Support\Carbon::parse($lastSlot->end_time)->format('g:i A') : '';
                            @endphp
                            <div class="rounded-xl border border-ink-100 dark:border-ink-800" x-data="{ open: false }">
                                <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 p-3 text-left text-sm">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-ink-900 dark:text-ink-100">{{ $booking->court->name }}</p>
                                        <p class="text-xs text-ink-500 dark:text-ink-400">{{ $dateLine }}, {{ $timeLine }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="font-mono text-xs text-ink-500 dark:text-ink-400">₱{{ number_format($booking->total_price, 2) }}</span>
                                        <i class="ph ph-caret-down text-sm text-ink-400 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                                    </div>
                                </button>

                                <div x-show="open" x-cloak class="border-t border-ink-100 p-3 text-center dark:border-ink-800">
                                    @if ($booking->status === 'confirmed')
                                        <p class="text-xs text-emerald-700 dark:text-emerald-400">Confirmed — use the one QR code below for the whole order.</p>
                                    @elseif ($booking->status === 'pending_payment')
                                        <p class="text-xs text-ink-500 dark:text-ink-400">Awaiting payment confirmation, along with the rest of the order.</p>
                                    @elseif ($booking->status === 'rejected')
                                        <p class="text-xs text-rose-600 dark:text-rose-400">This session's payment was not confirmed.</p>
                                    @elseif ($booking->status === 'cancelled')
                                        <p class="text-xs text-ink-500 dark:text-ink-400">This session was cancelled.</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-ink-100 pt-4 dark:border-ink-800">
                        <p class="text-sm font-semibold text-ink-950 dark:text-white">Total</p>
                        <p class="font-display text-xl font-semibold text-ink-950 dark:text-white">₱{{ number_format($order->total_price, 2) }}</p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">
                    One check-in code covers every session — see it under Status once your order is confirmed.
                </p>
            </div>

            {{-- Status + payment --}}
            <div class="lg:col-span-3">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-ink-100 bg-white px-4 py-3 dark:border-ink-800 dark:bg-ink-900">
                    <div class="flex items-center gap-2">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $statusMeta['classes'] }}">
                            <i class="ph {{ $statusMeta['icon'] }} text-lg"></i>
                        </span>
                        <div>
                            <p class="font-display text-base font-semibold text-ink-950 dark:text-white">{{ $statusMeta['label'] }}</p>
                            @if ($order->status === 'pending_payment' && ! $order->gcash_reference)
                                <p class="text-xs text-ink-500 dark:text-ink-400">Complete payment to confirm all sessions above.</p>
                            @endif
                        </div>
                    </div>

                    @if ($order->status === 'pending_payment' && ! $order->gcash_reference)
                        <div
                            x-data="{
                                deadline: new Date('{{ $order->created_at->copy()->addMinutes($paymentHoldMinutes)->toIso8601String() }}').getTime(),
                                remaining: '',
                                tick() {
                                    const diff = Math.max(0, this.deadline - Date.now());
                                    const m = Math.floor(diff / 60000);
                                    const s = Math.floor((diff % 60000) / 1000);
                                    this.remaining = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                                }
                            }"
                            x-init="tick(); setInterval(() => tick(), 1000)"
                            class="flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 dark:border-amber-800 dark:bg-amber-950"
                        >
                            <i class="ph ph-clock text-lg text-amber-600 dark:text-amber-400"></i>
                            <div>
                                <p class="text-[10px] font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-400">Pay within</p>
                                <p class="font-mono text-sm font-bold text-amber-800 dark:text-amber-300" x-text="remaining"></p>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($order->status === 'pending_payment' && ! $order->gcash_reference)
                    <div
                        x-data="{
                            step: {{ $errors->hasAny(['gcash_reference', 'proof_of_payment']) ? 3 : ($paymentMethods->count() > 1 ? 1 : 2) }},
                            methods: {{ $paymentMethods->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'account_number' => $m->account_number, 'account_name' => $m->account_name, 'instructions' => $m->instructions, 'qr_url' => $m->qrUrl()])->values()->toJson() }},
                            selectedId: {{ $paymentMethods->first()?->id ?? 'null' }},
                            get selected() { return this.methods.find(m => m.id === this.selectedId) ?? null; }
                        }"
                        class="mt-4 rounded-2xl border border-ink-100 bg-white p-5 dark:border-ink-800 dark:bg-ink-900"
                    >
                        @if ($paymentMethods->isEmpty())
                            <p class="text-sm text-ink-500 dark:text-ink-400">No payment methods are set up yet. Please contact us to complete your booking.</p>
                        @else
                            <div class="flex items-center">
                                <template x-for="(label, index) in ['Payment Method', 'Pay', 'Upload Proof']" :key="label">
                                    <div class="flex flex-1 items-center last:flex-none">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors"
                                                :class="step >= index + 1 ? 'bg-accent-500 text-ink-950' : 'bg-ink-100 text-ink-400 dark:bg-ink-800'"
                                                x-text="index + 1"
                                            ></span>
                                            <span class="hidden text-sm font-medium sm:inline" :class="step === index + 1 ? 'text-ink-950 dark:text-white' : 'text-ink-400'" x-text="label"></span>
                                        </div>
                                        <div class="mx-3 h-px flex-1" :class="step > index + 1 ? 'bg-accent-500' : 'bg-ink-100 dark:bg-ink-800'" x-show="index < 2"></div>
                                    </div>
                                </template>
                            </div>

                            <div x-show="step === 1" class="mt-6">
                                <p class="text-sm font-semibold text-ink-950 dark:text-white">Choose a payment method</p>
                                <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">Select how you'd like to pay the ₱{{ number_format($order->total_price, 2) }} total.</p>

                                <div class="mt-4 space-y-2">
                                    <template x-for="method in methods" :key="method.id">
                                        <label
                                            class="flex cursor-pointer items-center justify-between rounded-xl border p-4 transition-colors"
                                            :class="selectedId === method.id ? 'border-accent-400 bg-accent-50 dark:border-accent-700 dark:bg-accent-950' : 'border-ink-200 hover:border-ink-300 dark:border-ink-700'"
                                        >
                                            <div>
                                                <span class="inline-block rounded-full bg-ink-950 px-2.5 py-1 text-xs font-semibold text-white dark:bg-white dark:text-ink-950" x-text="method.name"></span>
                                                <p class="mt-2 font-mono text-sm font-semibold text-ink-900 dark:text-ink-100" x-text="method.account_number"></p>
                                                <p class="text-xs text-ink-500 dark:text-ink-400" x-text="method.account_name || 'Kitchen Line'"></p>
                                            </div>
                                            <input type="radio" :value="method.id" x-model.number="selectedId" class="h-4 w-4 text-accent-600">
                                        </label>
                                    </template>
                                </div>

                                <button type="button" @click="step = 2" class="mt-5 w-full rounded-full bg-ink-950 px-6 py-3 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950">
                                    Continue
                                </button>
                            </div>

                            <div x-show="step === 2" x-cloak class="mt-6">
                                <p class="text-sm font-semibold text-ink-950 dark:text-white">Send your payment</p>
                                <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">
                                    Send ₱{{ number_format($order->total_price, 2) }} using <span x-text="selected?.name"></span> — this covers all {{ $order->bookings->count() }} sessions.
                                </p>

                                <div class="mt-4 flex items-center justify-between rounded-xl bg-ink-100/60 px-4 py-3 dark:bg-ink-800/60">
                                    <span class="font-mono text-sm text-ink-800 dark:text-ink-200" x-text="selected?.account_number"></span>
                                    <span class="text-xs text-ink-500 dark:text-ink-400" x-text="selected?.account_name || 'Kitchen Line'"></span>
                                </div>
                                <template x-if="selected?.qr_url">
                                    <img :src="selected.qr_url" alt="Payment QR code" class="mx-auto mt-3 w-40 rounded-xl border border-ink-100 dark:border-ink-800">
                                </template>
                                <template x-if="selected?.instructions">
                                    <p class="mt-3 text-xs text-ink-500 dark:text-ink-400" x-text="selected.instructions"></p>
                                </template>

                                <div class="mt-5 flex gap-2">
                                    <button type="button" @click="step = {{ $paymentMethods->count() > 1 ? 1 : 2 }}" x-show="{{ $paymentMethods->count() > 1 ? 'true' : 'false' }}" class="flex-1 rounded-full border border-ink-200 px-4 py-2.5 text-sm font-semibold text-ink-700 hover:border-ink-400 dark:border-ink-700 dark:text-ink-200">
                                        Back
                                    </button>
                                    <button type="button" @click="step = 3" class="flex-1 rounded-full bg-ink-950 px-4 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950">
                                        I've sent the payment
                                    </button>
                                </div>
                            </div>

                            <div x-show="step === 3" x-cloak class="mt-6">
                                <p class="text-sm font-semibold text-ink-950 dark:text-white">Confirm your payment</p>
                                <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">Enter your reference number to finish — one reference covers the whole order.</p>

                                <form method="POST" action="{{ route('order.public.gcash-reference', $order->receipt_token) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                                    @csrf
                                    <input type="hidden" name="payment_method_id" :value="selectedId">
                                    <div class="flex flex-col gap-2">
                                        <label for="gcash_reference" class="text-sm font-medium text-ink-800 dark:text-ink-200">GCash reference number</label>
                                        <input
                                            id="gcash_reference"
                                            type="text"
                                            name="gcash_reference"
                                            required
                                            value="{{ old('gcash_reference') }}"
                                            class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                                            placeholder="e.g. 0123456789012"
                                        >
                                        @error('gcash_reference')
                                            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <label for="proof_of_payment" class="text-sm font-medium text-ink-800 dark:text-ink-200">Proof of payment <span class="font-normal text-ink-400">(optional, speeds up review)</span></label>
                                        <input
                                            id="proof_of_payment"
                                            type="file"
                                            name="proof_of_payment"
                                            accept="image/*"
                                            class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm text-ink-950 file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink-700 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:file:bg-ink-800 dark:file:text-ink-200"
                                        >
                                        <p class="text-xs text-ink-400">A screenshot of your GCash receipt.</p>
                                        @error('proof_of_payment')
                                            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex gap-2">
                                        <button type="button" @click="step = 2" class="flex-1 rounded-full border border-ink-200 px-4 py-2.5 text-sm font-semibold text-ink-700 hover:border-ink-400 dark:border-ink-700 dark:text-ink-200">
                                            Back
                                        </button>
                                        <button type="submit" class="flex-1 rounded-full bg-accent-500 px-4 py-2.5 text-sm font-semibold text-ink-950 transition-transform active:scale-[0.98] hover:bg-accent-400">
                                            Submit reference
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                @elseif ($order->status === 'pending_payment' && $order->gcash_reference)
                    <div class="mt-4 rounded-2xl border border-ink-100 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                        <p class="text-sm text-ink-600 dark:text-ink-400">
                            We are checking your payment now and will confirm all {{ $order->bookings->count() }} sessions shortly.
                        </p>
                        <div class="mt-3 flex items-center justify-between rounded-xl bg-ink-100/60 px-4 py-3 dark:bg-ink-800/60">
                            <div>
                                <p class="text-xs text-ink-500 dark:text-ink-400">GCash reference</p>
                                <p class="font-mono text-sm text-ink-900 dark:text-ink-100">{{ $order->gcash_reference }}</p>
                            </div>
                        </div>

                        @if ($order->paymentProofUrl())
                            <div class="mt-4" x-data="{ lightbox: false, zoomed: false }">
                                <p class="text-xs text-ink-500 dark:text-ink-400">Proof of payment submitted</p>
                                <button type="button" @click="lightbox = true" class="mt-2 block">
                                    <img src="{{ $order->paymentProofUrl() }}" alt="Submitted proof of payment" class="w-32 cursor-zoom-in rounded-xl border border-ink-100 object-cover transition-opacity hover:opacity-90 dark:border-ink-800">
                                </button>

                                <div
                                    x-show="lightbox"
                                    x-cloak
                                    x-transition.opacity
                                    @click="lightbox = false; zoomed = false"
                                    @keydown.escape.window="lightbox = false; zoomed = false"
                                    class="fixed inset-0 z-50 bg-ink-950/80 p-4"
                                    :class="zoomed ? 'overflow-auto' : 'flex items-center justify-center'"
                                >
                                    <div class="fixed top-4 right-4 z-10 flex items-center gap-2">
                                        <button type="button" @click.stop="zoomed = !zoomed" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" :aria-label="zoomed ? 'Zoom out' : 'Zoom in'">
                                            <i class="ph text-xl" :class="zoomed ? 'ph-magnifying-glass-minus' : 'ph-magnifying-glass-plus'"></i>
                                        </button>
                                        <button type="button" @click="lightbox = false; zoomed = false" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Close">
                                            <i class="ph ph-x text-xl"></i>
                                        </button>
                                    </div>
                                    <img
                                        src="{{ $order->paymentProofUrl() }}"
                                        alt="Submitted proof of payment"
                                        @click.stop="zoomed = !zoomed"
                                        class="rounded-2xl shadow-2xl transition-all"
                                        :class="zoomed ? 'my-8 max-w-none cursor-zoom-out mx-auto' : 'max-h-[85vh] max-w-full object-contain cursor-zoom-in'"
                                    >
                                </div>
                            </div>
                        @endif
                    </div>
                @elseif ($order->status === 'confirmed')
                    <div
                        class="mt-4 rounded-2xl border border-ink-100 bg-white p-6 text-center dark:border-ink-800 dark:bg-ink-900"
                        x-data="{
                            downloadQr() {
                                const svg = this.$refs.qrSvg.querySelector('svg');
                                const svgData = new XMLSerializer().serializeToString(svg);
                                const svgUrl = URL.createObjectURL(new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' }));
                                const img = new Image();
                                img.onload = () => {
                                    const qrSize = 220;
                                    const pad = 28;
                                    const canvas = document.createElement('canvas');
                                    canvas.width = qrSize + pad * 2;
                                    canvas.height = qrSize + pad * 2 + 110;
                                    const ctx = canvas.getContext('2d');
                                    ctx.fillStyle = '#ffffff';
                                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                                    ctx.drawImage(img, pad, pad, qrSize, qrSize);
                                    URL.revokeObjectURL(svgUrl);

                                    ctx.textAlign = 'center';
                                    ctx.fillStyle = '#0f172a';
                                    ctx.font = 'bold 17px sans-serif';
                                    ctx.fillText(@js('Order #'.$order->id), canvas.width / 2, qrSize + pad + 28);

                                    ctx.font = '13px sans-serif';
                                    ctx.fillStyle = '#475569';
                                    ctx.fillText(@js($order->bookings->count().' sessions'), canvas.width / 2, qrSize + pad + 50);
                                    ctx.fillText(@js($order->contactName()), canvas.width / 2, qrSize + pad + 70);

                                    const a = document.createElement('a');
                                    a.href = canvas.toDataURL('image/png');
                                    a.download = @js('order-'.$order->id.'.png');
                                    a.click();
                                };
                                img.src = svgUrl;
                            }
                        }"
                    >
                        <p class="text-sm font-semibold text-ink-950 dark:text-white">Show this one code at the gate</p>
                        <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Covers all {{ $order->bookings->count() }} sessions in this order — save it and show it whenever you arrive.</p>
                        <div class="mx-auto mt-4 w-fit rounded-2xl bg-white p-3" x-ref="qrSvg">
                            {!! QrCode::format('svg')->size(220)->generate($order->checkin_token) !!}
                        </div>
                        @if ($order->checkin_token_expires_at)
                            <p class="mt-4 text-xs text-ink-500 dark:text-ink-400">
                                Valid until {{ $order->checkin_token_expires_at->format('g:i A, M j') }}
                            </p>
                        @endif
                        <button
                            type="button"
                            @click="downloadQr()"
                            class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-ink-950 px-5 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400"
                        >
                            <i class="ph ph-download-simple text-base"></i>
                            Save QR code
                        </button>
                    </div>
                @elseif ($order->status === 'rejected')
                    <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
                        <p class="font-semibold">We could not confirm this payment.</p>
                        @if ($order->rejection_reason)
                            <p class="mt-1">{{ $order->rejection_reason }}</p>
                        @endif
                    </div>
                @elseif ($order->status === 'cancelled')
                    <div class="mt-4 rounded-2xl border border-ink-100 bg-white p-5 text-sm text-ink-600 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-400">
                        <p>This booking was cancelled.</p>
                        @if ($order->cancellation_reason)
                            <p class="mt-1">{{ $order->cancellation_reason }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

</x-layouts.app>
