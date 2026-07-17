<x-layouts.admin :title="'Hero images'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Hero images</h1>
    </div>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
        These images rotate as a carousel behind the homepage hero. Add as many as you like — drag order with the arrows below.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif
    @error('images')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            {{ $message }}
        </div>
    @enderror

    <form method="POST" action="{{ route('admin.hero-images.store') }}" enctype="multipart/form-data" class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
        @csrf
        <input
            name="images[]"
            type="file"
            accept="image/*"
            multiple
            required
            class="flex-1 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-1 file:text-xs file:font-semibold dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100 dark:file:bg-ink-800"
        >
        <button type="submit" class="rounded-lg bg-accent-500 px-4 py-2 text-sm font-semibold text-white hover:bg-accent-400">
            Upload
        </button>
    </form>

    @if ($images->isEmpty())
        <p class="mt-8 text-sm text-ink-500 dark:text-ink-400">No hero images yet — the homepage will fall back to a placeholder photo until you add some.</p>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($images as $image)
            <div class="overflow-hidden rounded-2xl border border-ink-200 bg-white dark:border-ink-800 dark:bg-ink-900">
                <img src="{{ $image->url() }}" alt="Hero image" class="aspect-4/5 w-full object-cover">
                <div class="flex items-center justify-between gap-2 p-3">
                    <div class="flex items-center gap-1">
                        <form method="POST" action="{{ route('admin.hero-images.move-up', $image) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400" title="Move earlier">
                                <i class="ph ph-arrow-up text-sm"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.hero-images.move-down', $image) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400" title="Move later">
                                <i class="ph ph-arrow-down text-sm"></i>
                            </button>
                        </form>
                    </div>
                    <form method="POST" action="{{ route('admin.hero-images.destroy', $image) }}" onsubmit="return confirm('Remove this image?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-500 hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-400">
                            <i class="ph ph-trash text-sm"></i>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

</x-layouts.admin>
