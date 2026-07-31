<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#ffffff">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>
        <meta name="description" content="{{ $description ?? 'Book pickleball courts by the hour. Pick a time, pay by GCash, show your code at the door.' }}">

        <link rel="icon" href="{{ \App\Models\OperatingHours::current()->logoUrl() ?? asset('favicon.ico') }}">
        <link href="https://fonts.googleapis.com/css2?family=Alfa+Slab+One&display=swap" rel="stylesheet">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="bg-white text-ink-900 antialiased dark:bg-ink-950 dark:text-ink-50">
        @include('partials.nav')

        <main>
            {{ $slot }}
        </main>

        @unless ($hideFooter ?? false)
            @include('partials.footer')
        @endunless
    </body>
</html>
