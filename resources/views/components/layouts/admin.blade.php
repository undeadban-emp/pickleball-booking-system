<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#100f0d">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' — Admin' : 'Admin' }} · Kitchen Line</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-ink-100 text-ink-900 antialiased dark:bg-ink-950 dark:text-ink-50">
        <div class="flex min-h-dvh">
            @include('partials.admin-sidebar')

            <div class="flex min-w-0 flex-1 flex-col">
                @include('partials.admin-topbar')

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
