<x-layouts.app :title="'Log in to '.config('app.name')" :hide-footer="true">

    <section class="mx-auto flex max-w-lg flex-col justify-center px-4 py-10 sm:px-6">
        <div class="mb-5 text-center">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
                Welcome back
            </h1>
            <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">
                Log in to manage your bookings.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-accent-300 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-800 dark:bg-accent-950 dark:text-accent-200">
                {{ session('status') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ url('/login') }}"
            class="space-y-4 rounded-3xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:p-6"
            x-data="{ showPassword: false }"
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

            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-sm font-medium text-ink-800 dark:text-ink-200">Password</label>
                <div class="relative">
                    <input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 pr-10 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                        placeholder="Enter your password"
                    >
                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-ink-400 hover:text-ink-700 dark:hover:text-ink-200" tabindex="-1">
                        <i class="ph text-base" :class="showPassword ? 'ph-eye-slash' : 'ph-eye'"></i>
                    </button>
                </div>
                @error('password')
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-400">
                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-ink-300 text-accent-600 focus:ring-accent-500">
                Keep me signed in
            </label>

            <button
                type="submit"
                class="w-full rounded-full bg-accent-500 px-6 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400"
            >
                Log in
            </button>

            <p class="text-center text-sm text-ink-600 dark:text-ink-400">
                Don't have an account? <a href="{{ route('register') }}" class="font-semibold text-ink-950 underline dark:text-white">Create one</a>
            </p>
        </form>
    </section>

</x-layouts.app>
