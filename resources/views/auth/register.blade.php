<x-layouts.app :title="'Create your account — '.config('app.name')" :hide-footer="true">

    <section class="mx-auto flex max-w-lg flex-col justify-center px-4 py-10 sm:px-6">
        <div class="mb-5 text-center">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
                Create your account
            </h1>
            <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">
                Enter your details below to join the club.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-accent-300 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-800 dark:bg-accent-950 dark:text-accent-200">
                {{ session('status') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('register.store') }}"
            class="space-y-4 rounded-3xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:p-6"
            x-data="{
                password: '',
                confirm: '',
                showPassword: false,
                showConfirm: false,
                get hasLength() { return this.password.length >= 8; },
                get hasUpper() { return /[A-Z]/.test(this.password); },
                get hasNumber() { return /[0-9]/.test(this.password); },
                get hasSymbol() { return /[^A-Za-z0-9]/.test(this.password); }
            }"
        >
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-sm font-medium text-ink-800 dark:text-ink-200">Full Name</label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        autocomplete="name"
                        class="w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                        placeholder="John Doe"
                    >
                    @error('name')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="phone" class="text-sm font-medium text-ink-800 dark:text-ink-200">Phone <span class="font-normal text-ink-400">(optional)</span></label>
                    <input
                        id="phone"
                        type="tel"
                        name="phone"
                        value="{{ old('phone') }}"
                        autocomplete="tel"
                        class="w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                        placeholder="09XX XXX XXXX"
                    >
                    @error('phone')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-sm font-medium text-ink-800 dark:text-ink-200">Email Address</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="username"
                    class="w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                    placeholder="name@example.com"
                >
                @error('email')
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="password" class="text-sm font-medium text-ink-800 dark:text-ink-200">Password</label>
                    <div class="relative">
                        <input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            x-model="password"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 pr-10 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                            placeholder="Create a password"
                        >
                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-ink-400 hover:text-ink-700 dark:hover:text-ink-200" tabindex="-1">
                            <i class="ph text-base" :class="showPassword ? 'ph-eye-slash' : 'ph-eye'"></i>
                        </button>
                    </div>
                    @error('password')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password_confirmation" class="text-sm font-medium text-ink-800 dark:text-ink-200">Confirm Password</label>
                    <div class="relative">
                        <input
                            id="password_confirmation"
                            :type="showConfirm ? 'text' : 'password'"
                            name="password_confirmation"
                            x-model="confirm"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-xl border px-3.5 py-2.5 pr-10 text-sm text-ink-950 placeholder:text-ink-400 focus:ring-2 focus:outline-none dark:bg-ink-950 dark:text-white"
                            :class="confirm.length > 0 && confirm !== password
                                ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-200 dark:border-rose-800'
                                : 'border-ink-200 bg-white focus:border-accent-500 focus:ring-accent-200 dark:border-ink-700 dark:focus:ring-accent-900'"
                            placeholder="Confirm password"
                        >
                        <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-ink-400 hover:text-ink-700 dark:hover:text-ink-200" tabindex="-1">
                            <i class="ph text-base" :class="showConfirm ? 'ph-eye-slash' : 'ph-eye'"></i>
                        </button>
                    </div>
                    <p x-show="confirm.length > 0 && confirm !== password" x-cloak class="text-xs text-rose-600 dark:text-rose-400">Passwords do not match.</p>
                    @error('password_confirmation')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Compact inline requirement pills instead of a stacked checklist --}}
            <div class="flex flex-wrap gap-1.5">
                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[11px] font-medium transition-colors" :class="hasLength ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400' : 'border-ink-200 text-ink-400 dark:border-ink-700'">
                    <i class="ph text-xs" :class="hasLength ? 'ph-check' : 'ph-circle'"></i> 8+ characters
                </span>
                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[11px] font-medium transition-colors" :class="hasUpper ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400' : 'border-ink-200 text-ink-400 dark:border-ink-700'">
                    <i class="ph text-xs" :class="hasUpper ? 'ph-check' : 'ph-circle'"></i> Uppercase
                </span>
                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[11px] font-medium transition-colors" :class="hasNumber ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400' : 'border-ink-200 text-ink-400 dark:border-ink-700'">
                    <i class="ph text-xs" :class="hasNumber ? 'ph-check' : 'ph-circle'"></i> Number
                </span>
                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[11px] font-medium transition-colors" :class="hasSymbol ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400' : 'border-ink-200 text-ink-400 dark:border-ink-700'">
                    <i class="ph text-xs" :class="hasSymbol ? 'ph-check' : 'ph-circle'"></i> Symbol
                </span>
            </div>

            <button
                type="submit"
                class="w-full rounded-full bg-accent-500 px-6 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400"
            >
                Create Account
            </button>

            <p class="text-center text-sm text-ink-600 dark:text-ink-400">
                Already have an account? <a href="{{ route('login') }}" class="font-semibold text-ink-950 underline dark:text-white">Sign in</a>
            </p>
        </form>
    </section>

</x-layouts.app>
