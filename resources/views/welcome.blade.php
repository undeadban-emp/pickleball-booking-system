<x-layouts.app>

    @php
        $__facebookUrl = \App\Models\OperatingHours::current()->facebook_url;
    @endphp

    @if ($__facebookUrl)
        <a
            href="{{ $__facebookUrl }}"
            target="_blank"
            rel="noopener"
            aria-label="Visit us on Facebook"
            class="fixed right-4 bottom-4 z-40 flex h-11 w-11 items-center justify-center rounded-full shadow-lg transition-transform hover:scale-110 active:scale-95 sm:right-5 sm:bottom-5 sm:h-14 sm:w-14"
        >
            <svg viewBox="0 0 36 36" class="h-11 w-11 sm:h-14 sm:w-14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M36 18C36 8.0589 27.9411 0 18 0C8.0589 0 0 8.0589 0 18C0 26.9804 6.58128 34.4128 15.1875 35.7825V23.2031H10.6172V18H15.1875V14.0325C15.1875 9.52125 17.8759 7.03125 21.9863 7.03125C23.9547 7.03125 26.0156 7.38281 26.0156 7.38281V11.8125H23.7451C21.5081 11.8125 20.8125 13.2002 20.8125 14.625V18H25.8047L25.0079 23.2031H20.8125V35.7825C29.4187 34.4128 36 26.9804 36 18Z" fill="#1877F2"/>
                <path d="M25.0079 23.2031L25.8047 18H20.8125V14.625C20.8125 13.2002 21.5081 11.8125 23.7451 11.8125H26.0156V7.38281C26.0156 7.38281 23.9547 7.03125 21.9863 7.03125C17.8759 7.03125 15.1875 9.52125 15.1875 14.0325V18H10.6172V23.2031H15.1875V35.7825C16.1093 35.9265 17.0483 36 18 36C18.9517 36 19.8907 35.9265 20.8125 35.7825V23.2031H25.0079Z" fill="white"/>
            </svg>
        </a>
    @endif

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
                @if ($mapLabel)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-ink-300">
                        <i class="ph ph-map-pin text-accent-400"></i>
                        {{ $mapLabel }}
                    </span>
                @endif

                <h1 class="mt-4 font-display text-4xl font-semibold tracking-tight text-white md:text-5xl lg:text-6xl">
                    Book Your Perfect<br><span class="text-accent-400">Pickleball Court</span>
                </h1>
                <p class="mt-5 max-w-md text-base leading-relaxed text-ink-300">
                    @if ($activeCourtsCount > 1)
                        Choose from {{ $activeCourtsCount }} outdoor courts.
                    @else
                        Book our outdoor court.
                    @endif
                    <span class="font-semibold text-white">View real-time availability</span> at a glance, pay by GCash, and go.
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
    <section class="mx-auto max-w-7xl px-4 py-10 text-center sm:px-6 sm:py-14 lg:px-8">
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

    {{-- Rescheduling / weather policy reminder --}}
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col items-start gap-4 rounded-3xl border border-ink-100 bg-white p-6 shadow-[0_1px_2px_rgba(24,24,27,0.04)] sm:flex-row sm:items-center sm:p-7 dark:border-ink-800 dark:bg-ink-900">
            <span class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-accent-50 text-accent-600 dark:bg-accent-950 dark:text-accent-400">
                <span class="absolute inset-0 rounded-full bg-accent-400/50 animate-[ping_2.5s_cubic-bezier(0,0,0.2,1)_infinite] motion-reduce:animate-none dark:bg-accent-500/40"></span>
                <i class="ph ph-cloud-rain relative text-xl"></i>
            </span>
            <div>
                <p class="font-display text-base font-semibold text-ink-950 dark:text-white">Rained out? We've got you.</p>
                <p class="mt-1 text-sm leading-relaxed text-ink-500 dark:text-ink-400">
                    Our courts are open-air, so rescheduling only applies when weather cancels your session. For anything else, just get in touch.
                </p>
            </div>
        </div>
    </section>

    @include('partials.availability-widget')

    @if ($mapLat && $mapLng)
        <section class="mx-auto max-w-7xl px-4 py-10 text-center sm:px-6 sm:py-14 lg:px-8">
            <p class="flex items-center justify-center gap-2 text-xs font-semibold tracking-[0.18em] text-accent-700 uppercase dark:text-accent-400">
                <span class="h-px w-4 bg-accent-500"></span>
                Find us
                <span class="h-px w-4 bg-accent-500"></span>
            </p>
            @if ($mapLabel)
                <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-ink-950 md:text-3xl dark:text-white">
                    {{ $mapLabel }}
                </h2>
            @endif

            <div
                id="home-map"
                class="isolate relative mt-8 h-80 w-full overflow-hidden rounded-3xl border border-ink-100 sm:h-96 dark:border-ink-800"
            ></div>
        </section>

        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        @include('partials.map-styles-script')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            (function () {
                var lat = {{ $mapLat }};
                var lng = {{ $mapLng }};
                var style = window.MAP_STYLES[@js($mapStyle)] || window.MAP_STYLES.standard;

                var map = L.map('home-map', { scrollWheelZoom: true, touchZoom: true, tap: true }).setView([lat, lng], 15);
                L.tileLayer(style.url, style).addTo(map);
                L.marker([lat, lng]).addTo(map)@if($mapLabel).bindPopup(@js($mapLabel))@endif;
            })();
        </script>
    @elseif ($mapLabel)
        <section class="mx-auto max-w-7xl px-4 py-10 text-center sm:px-6 sm:py-14 lg:px-8">
            <p class="flex items-center justify-center gap-2 text-xs font-semibold tracking-[0.18em] text-accent-700 uppercase dark:text-accent-400">
                <span class="h-px w-4 bg-accent-500"></span>
                Find us
                <span class="h-px w-4 bg-accent-500"></span>
            </p>
            <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-ink-950 md:text-3xl dark:text-white">
                {{ $mapLabel }}
            </h2>

            <div class="mt-8 overflow-hidden rounded-3xl border border-ink-100 dark:border-ink-800">
                <iframe
                    src="https://www.google.com/maps?q={{ urlencode($mapLabel) }}&output=embed"
                    class="h-80 w-full sm:h-96"
                    style="border:0"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Location map"
                ></iframe>
            </div>
        </section>
    @endif

    @if ($galleryImages->isNotEmpty())
        <section
            class="mx-auto max-w-7xl px-4 py-10 text-center sm:px-6 sm:py-14 lg:px-8"
            x-data="{
                showAllPhotos: false,
                lightboxOpen: false,
                lightboxIndex: 0,
                photos: @js($galleryImages->map(fn ($image) => $image->url())),
                open(index) { this.lightboxIndex = index; this.lightboxOpen = true; },
                next() { this.lightboxIndex = (this.lightboxIndex + 1) % this.photos.length; },
                prev() { this.lightboxIndex = (this.lightboxIndex - 1 + this.photos.length) % this.photos.length; },
            }"
            @keydown.escape.window="lightboxOpen = false"
            @keydown.arrow-right.window="lightboxOpen && next()"
            @keydown.arrow-left.window="lightboxOpen && prev()"
        >
            <p class="flex items-center justify-center gap-2 text-xs font-semibold tracking-[0.18em] text-accent-700 uppercase dark:text-accent-400">
                <span class="h-px w-4 bg-accent-500"></span>
                Gallery
                <span class="h-px w-4 bg-accent-500"></span>
            </p>
            <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-ink-950 md:text-3xl dark:text-white">
                A look around
            </h2>

            <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                @foreach ($galleryImages as $image)
                    <button
                        type="button"
                        @click="open({{ $loop->index }})"
                        class="group aspect-square cursor-zoom-in overflow-hidden rounded-2xl border border-ink-100 dark:border-ink-800"
                        @if ($loop->index >= 8) x-show="showAllPhotos" x-cloak @endif
                    >
                        <img src="{{ $image->url() }}" alt="Facility photo" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                    </button>
                @endforeach
            </div>

            @if ($galleryImages->count() > 8)
                <button
                    type="button"
                    @click="showAllPhotos = !showAllPhotos"
                    class="mt-6 inline-flex items-center gap-1.5 rounded-full border border-ink-200 px-5 py-2.5 text-sm font-semibold text-ink-700 transition-colors hover:border-ink-400 dark:border-ink-700 dark:text-ink-300 dark:hover:border-ink-500"
                >
                    <span x-text="showAllPhotos ? 'Show less' : 'Show more'"></span>
                    <i class="ph text-base" :class="showAllPhotos ? 'ph-caret-up' : 'ph-caret-down'"></i>
                </button>
            @endif

            <div
                x-show="lightboxOpen"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-md"
                @click.self="lightboxOpen = false"
            >
                <button
                    type="button"
                    @click="lightboxOpen = false"
                    class="absolute top-4 right-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
                    aria-label="Close"
                >
                    <i class="ph ph-x text-xl"></i>
                </button>

                <button
                    type="button"
                    @click.stop="prev()"
                    x-show="photos.length > 1"
                    class="absolute left-2 top-1/2 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-white/15 text-white shadow-lg backdrop-blur-sm transition-colors hover:bg-white/30 sm:left-6"
                    aria-label="Previous photo"
                >
                    <i class="ph ph-caret-left text-2xl"></i>
                </button>

                <img
                    :src="photos[lightboxIndex]"
                    alt="Facility photo"
                    class="max-h-[85vh] max-w-full rounded-lg object-contain shadow-2xl"
                    @click.stop
                >

                <button
                    type="button"
                    @click.stop="next()"
                    x-show="photos.length > 1"
                    class="absolute right-2 top-1/2 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-white/15 text-white shadow-lg backdrop-blur-sm transition-colors hover:bg-white/30 sm:right-6"
                    aria-label="Next photo"
                >
                    <i class="ph ph-caret-right text-2xl"></i>
                </button>

                <p class="absolute bottom-4 left-1/2 -translate-x-1/2 text-sm text-white/70" x-text="(lightboxIndex + 1) + ' / ' + photos.length"></p>
            </div>
        </section>
    @endif

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
