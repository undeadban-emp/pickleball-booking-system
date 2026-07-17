<x-layouts.app :title="'Book a court — Kitchen Line'">

    <section class="mx-auto max-w-5xl px-4 py-14 sm:px-6 lg:px-8">
        <h1 class="font-display text-3xl font-semibold tracking-tight text-ink-950 md:text-4xl dark:text-white">
            Pick a court
        </h1>
        <p class="mt-2 text-sm text-ink-600 dark:text-ink-400">
            Choose where you want to play, then pick a date and time.
        </p>

        @if ($courts->isEmpty())
            <p class="mt-8 text-sm text-ink-500 dark:text-ink-400">No courts are open for booking right now. Check back soon.</p>
        @else
            <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2">
                @foreach ($courts as $court)
                    <a
                        href="{{ route('book.show', $court) }}"
                        class="group flex items-center justify-between rounded-2xl border border-ink-100 bg-white p-5 transition-colors hover:border-accent-400 dark:border-ink-800 dark:bg-ink-900"
                    >
                        <div>
                            <p class="font-display text-lg font-semibold text-ink-950 dark:text-white">{{ $court->name }}</p>
                            @if ($court->location)
                                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $court->location }}</p>
                            @endif
                            <p class="mt-2 text-sm font-medium text-accent-700 dark:text-accent-400">
                                From ₱{{ number_format($court->default_price, 0) }} / hour
                            </p>
                        </div>
                        <i class="ph ph-arrow-right text-xl text-ink-400 transition-transform group-hover:translate-x-1 group-hover:text-accent-600"></i>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

</x-layouts.app>
