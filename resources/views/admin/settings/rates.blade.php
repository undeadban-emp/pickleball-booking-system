<x-layouts.admin :title="'Court Rates'">

    <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Court Rates</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
        Set the hourly rate for each court. New slots use the updated rate — slots already generated keep their original price.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 space-y-3">
        @forelse ($courts as $court)
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <div>
                    <p class="font-display text-base font-semibold text-ink-950 dark:text-white">{{ $court->name }}</p>
                    <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $court->location ?? 'No location set' }}</p>
                </div>

                <form method="POST" action="{{ route('admin.settings.rates.update', $court) }}" class="flex items-center gap-2">
                    @csrf
                    @method('PUT')
                    <span class="text-sm text-ink-500 dark:text-ink-400">₱</span>
                    <input
                        name="default_price"
                        type="number"
                        step="0.01"
                        min="0"
                        value="{{ old('default_price', $court->default_price) }}"
                        required
                        class="w-28 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100"
                    >
                    <span class="text-sm text-ink-500 dark:text-ink-400">/ hr</span>
                    <button type="submit" class="rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">Save</button>
                </form>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-ink-200 p-6 text-center text-sm text-ink-500 dark:border-ink-800 dark:text-ink-400">
                No courts yet. Add one from the Courts page first.
            </div>
        @endforelse
    </div>

</x-layouts.admin>
