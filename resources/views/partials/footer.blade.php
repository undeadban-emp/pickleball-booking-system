@php
    $__brandSettings = \App\Models\OperatingHours::current();
@endphp

<footer class="bg-accent-500">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm font-medium text-white sm:flex-row sm:px-6 lg:px-8">
        <p>&copy; {{ date('Y') }} {{ $__brandSettings->show_brand_text ? $__brandSettings->brand_text : 'Kitchen Line' }}. All rights reserved.</p>
        <p>Made for players, not spreadsheets.</p>
    </div>
</footer>
