<x-layouts.app :title="'Forgot Password — '.config('app.name')" :hide-footer="true">

    <section class="mx-auto flex max-w-lg flex-col justify-center px-4 py-10 sm:px-6">
        <div class="mb-5 text-center">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
                Forgot your password?
            </h1>
            <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">
                Enter your email and we'll send you a 6-digit code to reset it.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-accent-300 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-800 dark:bg-accent-950 dark:text-accent-200">
                {{ session('status') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('password.send-code') }}"
            class="space-y-4 rounded-3xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:p-6"
        >
            @csrf

            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-sm font-medium text-ink-800 dark:text-ink-200">Email Address</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                    placeholder="name@example.com"
                >
                @error('email')
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="w-full rounded-full bg-accent-500 px-6 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400"
            >
                Send Reset Code
            </button>

            <p class="text-center text-sm text-ink-600 dark:text-ink-400">
                Remembered your password? <a href="{{ route('login') }}" class="font-semibold text-ink-950 underline dark:text-white">Back to login</a>
            </p>
        </form>
    </section>

</x-layouts.app>
