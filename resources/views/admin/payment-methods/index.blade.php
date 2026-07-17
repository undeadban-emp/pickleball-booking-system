<x-layouts.admin :title="'Payment methods'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Payment methods</h1>
        <details class="group">
            <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                <i class="ph ph-plus text-base"></i>
                Add method
            </summary>
            <form method="POST" action="{{ route('admin.payment-methods.store') }}" enctype="multipart/form-data" class="mt-4 grid grid-cols-1 gap-3 rounded-2xl border border-ink-200 bg-white p-4 sm:grid-cols-2 dark:border-ink-800 dark:bg-ink-900">
                @csrf
                <input name="name" type="text" placeholder="Name, e.g. GCash or Bank Transfer - RCBC" required class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input name="account_number" type="text" placeholder="Account / mobile number" required class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input name="account_name" type="text" placeholder="Account name (optional)" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input name="qr" type="file" accept="image/*" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-1 file:text-xs file:font-semibold dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100 dark:file:bg-ink-800">
                <textarea name="instructions" rows="2" placeholder="Instructions shown to customers (optional)" class="col-span-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100"></textarea>
                <button type="submit" class="col-span-full rounded-lg bg-accent-500 px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-accent-400">Create</button>
            </form>
        </details>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif
    @error('payment_method')
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            {{ $message }}
        </div>
    @enderror

    @if ($methods->isEmpty())
        <p class="mt-8 text-sm text-ink-500 dark:text-ink-400">No payment methods yet. Add one so customers can pay.</p>
    @endif

    <div class="mt-6 space-y-4">
        @foreach ($methods as $method)
            <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-4">
                        @if ($method->qrUrl())
                            <img src="{{ $method->qrUrl() }}" alt="{{ $method->name }} QR" class="h-16 w-16 rounded-lg border border-ink-100 object-cover dark:border-ink-800">
                        @endif
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-display text-lg font-semibold text-ink-950 dark:text-white">{{ $method->name }}</p>
                                @if ($method->is_active)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Active</span>
                                @else
                                    <span class="rounded-full bg-ink-200 px-2.5 py-0.5 text-xs font-semibold text-ink-600 dark:bg-ink-800 dark:text-ink-400">Inactive</span>
                                @endif
                            </div>
                            <p class="mt-1 font-mono text-sm text-ink-600 dark:text-ink-400">{{ $method->account_number }}</p>
                            @if ($method->account_name)
                                <p class="text-xs text-ink-500 dark:text-ink-400">{{ $method->account_name }}</p>
                            @endif
                            @if ($method->instructions)
                                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $method->instructions }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <details class="group">
                            <summary class="cursor-pointer list-none rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-700 hover:border-ink-400 dark:border-ink-700 dark:text-ink-300">Edit</summary>
                            <form method="POST" action="{{ route('admin.payment-methods.update', $method) }}" enctype="multipart/form-data" class="mt-3 grid grid-cols-1 gap-2 rounded-xl border border-ink-100 p-3 sm:grid-cols-2 dark:border-ink-800">
                                @csrf
                                @method('PUT')
                                <input name="name" type="text" value="{{ $method->name }}" required class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                <input name="account_number" type="text" value="{{ $method->account_number }}" required class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                <input name="account_name" type="text" value="{{ $method->account_name }}" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                <input name="qr" type="file" accept="image/*" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-1 file:text-xs file:font-semibold dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100 dark:file:bg-ink-800">
                                <textarea name="instructions" rows="2" class="col-span-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">{{ $method->instructions }}</textarea>
                                <button type="submit" class="col-span-full rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950">Save</button>
                            </form>
                        </details>

                        <form method="POST" action="{{ route('admin.payment-methods.toggle-active', $method) }}">
                            @csrf
                            @if ($method->is_active)
                                <button type="submit" class="rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-700 hover:border-ink-400 dark:border-ink-700 dark:text-ink-300">Deactivate</button>
                            @else
                                <button type="submit" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950">Activate</button>
                            @endif
                        </form>

                        <form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" onsubmit="return confirm('Remove this payment method?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-500 hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-400">
                                <i class="ph ph-trash text-sm"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</x-layouts.admin>
