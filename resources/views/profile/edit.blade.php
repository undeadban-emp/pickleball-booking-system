<x-layouts.app :title="'Edit profile'">

    <section class="mx-auto max-w-2xl px-4 py-14 sm:px-6 lg:px-8">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left"></i>
            Back
        </a>

        <h1 class="mt-4 font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white sm:text-3xl">Edit profile</h1>
        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Update your account details or change your password.</p>

        @if (session('status'))
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-6 rounded-2xl border border-ink-100 bg-white p-5 dark:border-ink-800 dark:bg-ink-900 sm:p-6">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Account details</p>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-4 flex flex-col gap-4">
                @csrf
                @method('PUT')

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-xs font-medium text-ink-500 dark:text-ink-400">Full name</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        value="{{ old('name', $user->name) }}"
                        class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white"
                    >
                    @error('name')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-xs font-medium text-ink-500 dark:text-ink-400">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            value="{{ old('email', $user->email) }}"
                            class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white"
                        >
                        @error('email')
                            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="phone" class="text-xs font-medium text-ink-500 dark:text-ink-400">Phone</label>
                        <input
                            id="phone"
                            name="phone"
                            type="tel"
                            value="{{ old('phone', $user->phone) }}"
                            placeholder="09XX-XXX-XXXX"
                            class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white"
                        >
                        @error('phone')
                            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button
                    type="submit"
                    class="mt-2 w-fit rounded-full bg-ink-950 px-6 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400"
                >
                    Save changes
                </button>
            </form>
        </div>

        <div class="mt-6 rounded-2xl border border-ink-100 bg-white p-5 dark:border-ink-800 dark:bg-ink-900 sm:p-6">
            <p class="text-sm font-semibold text-ink-950 dark:text-white">Change password</p>

            <form method="POST" action="{{ route('profile.password') }}" class="mt-4 flex flex-col gap-4">
                @csrf
                @method('PUT')

                <div class="flex flex-col gap-1.5">
                    <label for="current_password" class="text-xs font-medium text-ink-500 dark:text-ink-400">Current password</label>
                    <input
                        id="current_password"
                        name="current_password"
                        type="password"
                        required
                        class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white"
                    >
                    @error('current_password')
                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-xs font-medium text-ink-500 dark:text-ink-400">New password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white"
                        >
                        @error('password')
                            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password_confirmation" class="text-xs font-medium text-ink-500 dark:text-ink-400">Confirm new password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white"
                        >
                    </div>
                </div>

                <p class="text-xs text-ink-400">At least 8 characters, with uppercase, lowercase, a number, and a symbol.</p>

                <button
                    type="submit"
                    class="mt-2 w-fit rounded-full border border-ink-200 px-6 py-2.5 text-sm font-semibold text-ink-700 transition-colors hover:border-ink-400 dark:border-ink-700 dark:text-ink-300 dark:hover:border-ink-500"
                >
                    Update password
                </button>
            </form>
        </div>
    </section>

</x-layouts.app>
