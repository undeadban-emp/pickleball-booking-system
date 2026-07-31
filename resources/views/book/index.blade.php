<x-layouts.app :title="'Book a court — Kitchen Line'" :hide-footer="true">

    @auth
        <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
            <a href="{{ route('bookings.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
                <i class="ph ph-arrow-left"></i>
                Back to my bookings
            </a>
        </div>
    @endauth

    @include('partials.availability-widget')

</x-layouts.app>
