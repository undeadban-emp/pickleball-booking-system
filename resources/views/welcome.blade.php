<x-layouts.app>

    {{-- Hero: dark, badge-led, full-width image carousel background --}}
    <section class="relative overflow-hidden bg-ink-950">
        @if ($heroImages->isNotEmpty())
            <div
                x-data="{
                    slides: {{ $heroImages->map(fn ($i) => $i->url())->toJson() }},
                    active: 0,
                    next() { this.active = (this.active + 1) % this.slides.length; }
                }"
                x-init="slides.length > 1 && setInterval(() => next(), 5000)"
                class="absolute inset-0"
            >
                <template x-for="(slide, index) in slides" :key="index">
                    <img
                        :src="slide"
                        alt="Kitchen Line court"
                        class="absolute inset-0 h-full w-full object-cover transition-opacity duration-1000"
                        :class="active === index ? 'opacity-100' : 'opacity-0'"
                        loading="eager"
                    >
                </template>

                <div x-show="slides.length > 1" class="absolute inset-x-0 bottom-5 z-10 flex items-center justify-center gap-1.5">
                    <template x-for="(slide, index) in slides" :key="index">
                        <button
                            type="button"
                            @click="active = index"
                            class="h-1.5 rounded-full transition-all"
                            :class="active === index ? 'w-5 bg-white' : 'w-1.5 bg-white/40'"
                            :aria-label="'Go to slide ' + (index + 1)"
                        ></button>
                    </template>
                </div>
            </div>
        @else
            <img
                src="https://picsum.photos/seed/kitchenline-hero-court/1600/900"
                alt="Player mid-rally on an outdoor pickleball court"
                class="absolute inset-0 h-full w-full object-cover"
                loading="eager"
            >
        @endif

        <div class="absolute inset-0 bg-linear-to-r from-ink-950/95 via-ink-950/70 to-ink-950/1"></div>

        <div class="relative mx-auto max-w-7xl px-4 pt-14 pb-16 sm:px-6 md:pb-20 lg:px-8">
            <div class="max-w-xl">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-ink-300">
                    <i class="ph ph-map-pin text-accent-400"></i>
                    Riverside strip, Manila
                </span>

                <h1 class="mt-4 font-display text-4xl font-semibold tracking-tight text-white md:text-5xl lg:text-6xl">
                    Book Your Perfect<br><span class="text-accent-400">Pickleball Court</span>
                </h1>
                <p class="mt-5 max-w-md text-base leading-relaxed text-ink-300">
                    Choose from three outdoor courts. <span class="font-semibold text-white">View real-time availability</span> at a glance, pay by GCash, and go.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="#availability" class="inline-flex items-center gap-2 rounded-full bg-accent-500 px-6 py-3 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400">
                        <i class="ph ph-calendar-check text-base"></i>
                        Browse courts
                    </a>
                   
                    @guest
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-full border border-white/15 px-6 py-3 text-sm font-semibold text-white transition-colors hover:border-white/30">
                            Log in
                        </a>
                    @else
                        <a href="{{ route('bookings.index') }}" class="inline-flex items-center gap-2 rounded-full border border-white/15 px-6 py-3 text-sm font-semibold text-white transition-colors hover:border-white/30">
                            My bookings
                        </a>
                    @endguest
                </div>
            </div>
        </div>
    </section>

    {{-- Facilities & amenities --}}
    <section class="mx-auto max-w-7xl px-4 py-16 text-center sm:px-6 lg:px-8">
        <p class="flex items-center justify-center gap-2 text-xs font-semibold tracking-[0.18em] text-accent-700 uppercase dark:text-accent-400">
            <span class="h-px w-4 bg-accent-500"></span>
            Facilities &amp; amenities
        </p>
        <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-ink-950 md:text-3xl dark:text-white">
            Everything you need for the perfect game
        </h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-ink-500 dark:text-ink-400">
            A complete, well-kept facility designed for comfort and serious play.
        </p>

        <div class="mt-8 grid grid-cols-2 gap-6 rounded-3xl border border-ink-100 bg-white p-6 sm:grid-cols-3 sm:gap-8 sm:p-8 lg:grid-cols-6 dark:border-ink-800 dark:bg-ink-900">
            @foreach ([
                ['icon' => 'ph-tennis-ball', 'label' => '3 Outdoor Courts'],
                ['icon' => 'ph-clock', 'label' => 'Open 6am to 10pm'],
                ['icon' => 'ph-drop', 'label' => 'Shower &amp; Restrooms'],
                ['icon' => 'ph-armchair', 'label' => 'Lounge Area'],
                ['icon' => 'ph-video-camera', 'label' => 'CCTV Monitored'],
                ['icon' => 'ph-car', 'label' => 'Free Parking'],
            ] as $amenity)
                <div class="flex flex-col items-center gap-2">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-accent-50 text-accent-600 dark:bg-accent-950 dark:text-accent-400">
                        <i class="ph {{ $amenity['icon'] }} text-xl"></i>
                    </span>
                    <p class="text-xs font-medium text-ink-700 dark:text-ink-300">{!! $amenity['label'] !!}</p>
                </div>
            @endforeach
        </div>
    </section>

    @include('partials.availability-widget')

    {{-- How it works
    <section id="how-it-works" class="border-t border-ink-100 bg-ink-100/40 dark:border-ink-800 dark:bg-ink-900/40">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <p class="text-xs font-semibold tracking-[0.18em] text-accent-700 uppercase dark:text-accent-400">How it works</p>
            <h2 class="mt-3 max-w-xl font-display text-3xl font-semibold tracking-tight text-ink-950 md:text-4xl dark:text-white">
                Three steps, then you're playing.
            </h2>

            <div class="mt-12 grid grid-cols-1 gap-10 md:grid-cols-3 md:gap-8">
                <div class="border-t-2 border-accent-500 pt-5">
                    <span class="font-display text-sm font-semibold text-ink-400 dark:text-ink-500">01</span>
                    <h3 class="mt-2 flex items-center gap-2 text-lg font-semibold text-ink-950 dark:text-white">
                        <i class="ph ph-calendar-check text-xl text-accent-600 dark:text-accent-400"></i>
                        Pick your slots
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-600 dark:text-ink-400">
                        Choose a court and one or more back-to-back hours that work for you.
                    </p>
                </div>

                <div class="border-t-2 border-accent-500 pt-5">
                    <span class="font-display text-sm font-semibold text-ink-400 dark:text-ink-500">02</span>
                    <h3 class="mt-2 flex items-center gap-2 text-lg font-semibold text-ink-950 dark:text-white">
                        <i class="ph ph-qr-code text-xl text-accent-600 dark:text-accent-400"></i>
                        Pay by GCash
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-600 dark:text-ink-400">
                        Scan the QR, send payment, and submit your reference number.
                    </p>
                </div>

                <div class="border-t-2 border-accent-500 pt-5">
                    <span class="font-display text-sm font-semibold text-ink-400 dark:text-ink-500">03</span>
                    <h3 class="mt-2 flex items-center gap-2 text-lg font-semibold text-ink-950 dark:text-white">
                        <i class="ph ph-door-open text-xl text-accent-600 dark:text-accent-400"></i>
                        Show up and play
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-600 dark:text-ink-400">
                        Once we confirm payment, your check-in code is ready to scan at the gate.
                    </p>
                </div>
            </div>
        </div>
    </section> --}}

    


</x-layouts.app>
