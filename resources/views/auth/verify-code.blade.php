<x-layouts.app :title="'Verify Code — '.config('app.name')" :hide-footer="true">

    <section class="mx-auto flex max-w-lg flex-col justify-center px-4 py-10 sm:px-6">
        <div class="mb-5 text-center">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
                Enter verification code
            </h1>
            <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">
                We sent a 6-digit code to <span class="font-semibold text-ink-900 dark:text-white">{{ session('password_reset_email') }}</span>.
                The code expires in 10 minutes.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-accent-300 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-800 dark:bg-accent-950 dark:text-accent-200">
                {{ session('status') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('password.verify.submit') }}"
            class="space-y-4 rounded-3xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:p-6"
            x-data="{
                digits: ['', '', '', '', '', ''],
                get code() { return this.digits.join(''); },
                onInput(e, i) {
                    const v = e.target.value.replace(/[^0-9]/g, '').slice(-1);
                    this.digits[i] = v;
                    e.target.value = v;
                    if (v && e.target.nextElementSibling) {
                        e.target.nextElementSibling.focus();
                    }
                },
                onKeydown(e, i) {
                    if (e.key === 'Backspace' && !this.digits[i] && e.target.previousElementSibling) {
                        e.target.previousElementSibling.focus();
                    }
                },
                onPaste(e) {
                    const text = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                    const inputs = $el.querySelectorAll('[data-digit]');
                    for (let i = 0; i < 6; i++) {
                        this.digits[i] = text[i] || '';
                        inputs[i].value = this.digits[i];
                    }
                    if (text.length) {
                        inputs[Math.min(text.length, 6) - 1].focus();
                    }
                }
            }"
        >
            @csrf
            <input type="hidden" name="code" :value="code">

            <div class="flex justify-center gap-2" @paste.prevent="onPaste">
                <template x-for="i in 6" :key="i">
                    <input
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="1"
                        data-digit
                        x-bind:autofocus="i === 1"
                        @input="onInput($event, i - 1)"
                        @keydown="onKeydown($event, i - 1)"
                        class="h-12 w-12 rounded-xl border border-ink-200 bg-white text-center text-lg font-semibold text-ink-950 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white dark:focus:ring-accent-900"
                    >
                </template>
            </div>
            @error('code')
                <p class="text-center text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror

            <button
                type="submit"
                class="w-full rounded-full bg-accent-500 px-6 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400"
            >
                Verify Code
            </button>
        </form>

        <form method="POST" action="{{ route('password.send-code') }}" class="mt-3 text-center">
            @csrf
            <input type="hidden" name="email" value="{{ session('password_reset_email') }}">
            <p class="text-sm text-ink-600 dark:text-ink-400">
                Didn't get a code?
                <button type="submit" class="font-semibold text-ink-950 underline dark:text-white">Resend code</button>
            </p>
        </form>
    </section>

</x-layouts.app>
