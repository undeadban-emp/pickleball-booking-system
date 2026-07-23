<x-layouts.admin :title="'Album'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Album</h1>
    </div>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
        Shown as a photo gallery on the homepage, below the booking widget. Pick several files at once — they upload one at a time, and are converted to WebP automatically.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div
        x-data="galleryUpload({ uploadUrl: '{{ route('admin.gallery-images.store') }}' })"
        class="mt-6 rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900"
    >
        <div class="flex flex-wrap items-center gap-3">
            <input
                type="file"
                accept="image/*"
                multiple
                @change="onFilesSelected($event.target.files); $event.target.value = ''"
                class="flex-1 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-1 file:text-xs file:font-semibold dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100 dark:file:bg-ink-800"
            >
        </div>

        <template x-if="queue.length > 0">
            <div class="mt-4 space-y-1.5">
                <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">
                    Uploading <span x-text="doneCount"></span> / <span x-text="queue.length"></span>
                </p>
                <template x-for="item in queue" :key="item.name">
                    <div class="flex items-center justify-between gap-2 rounded-lg bg-ink-50 px-3 py-2 text-xs dark:bg-ink-800/50">
                        <span class="truncate text-ink-700 dark:text-ink-300" x-text="item.name"></span>
                        <span
                            class="shrink-0 font-semibold"
                            :class="{
                                'text-ink-400': item.status === 'pending',
                                'text-accent-600 dark:text-accent-400': item.status === 'uploading',
                                'text-emerald-600 dark:text-emerald-400': item.status === 'done',
                                'text-rose-600 dark:text-rose-400': item.status === 'error',
                            }"
                            x-text="{ pending: 'Waiting…', uploading: 'Uploading…', done: 'Done', error: 'Failed' }[item.status]"
                        ></span>
                    </div>
                </template>
            </div>
        </template>
    </div>

    @if ($images->isEmpty())
        <p class="mt-8 text-sm text-ink-500 dark:text-ink-400">No album photos yet.</p>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($images as $image)
            <div class="overflow-hidden rounded-2xl border border-ink-200 bg-white dark:border-ink-800 dark:bg-ink-900">
                <img src="{{ $image->url() }}" alt="Album photo" class="aspect-4/3 w-full object-cover">
                <div class="flex items-center justify-between gap-2 p-3">
                    <div class="flex items-center gap-1">
                        <form method="POST" action="{{ route('admin.gallery-images.move-up', $image) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400" title="Move earlier">
                                <i class="ph ph-arrow-up text-sm"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.gallery-images.move-down', $image) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-ink-200 p-1.5 text-ink-500 hover:border-ink-400 hover:text-ink-800 dark:border-ink-700 dark:text-ink-400" title="Move later">
                                <i class="ph ph-arrow-down text-sm"></i>
                            </button>
                        </form>
                    </div>
                    <form method="POST" action="{{ route('admin.gallery-images.destroy', $image) }}" onsubmit="return confirm('Remove this image?');">
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
