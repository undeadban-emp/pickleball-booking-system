<x-layouts.admin :title="'Emails'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Emails</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Audit log of every email the system has attempted to send.</p>
        </div>
        <details class="group">
            <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                <i class="ph ph-plus text-base"></i>
                Add entry
            </summary>
            <form method="POST" action="{{ route('admin.settings.emails.store') }}" class="mt-4 grid grid-cols-1 gap-3 rounded-2xl border border-ink-200 bg-white p-4 sm:grid-cols-2 dark:border-ink-800 dark:bg-ink-900">
                @csrf
                <input name="email" type="email" placeholder="Recipient email" required
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <select name="status" required
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                    <option value="pending">Pending</option>
                    <option value="sent">Sent</option>
                    <option value="failed">Failed</option>
                </select>
                <textarea name="message" rows="2" placeholder="Message" required
                    class="col-span-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100"></textarea>
                <button type="submit" class="col-span-full rounded-lg bg-accent-500 px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-accent-400">Create</button>
            </form>
        </details>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 overflow-x-auto rounded-2xl border border-ink-200 dark:border-ink-800">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-ink-100 text-xs font-medium tracking-wide text-ink-500 uppercase dark:bg-ink-800 dark:text-ink-400">
                <tr>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Message</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Sent</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                @forelse ($emails as $log)
                    <tr class="hover:bg-ink-50 dark:hover:bg-ink-800/50">
                        <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-100">{{ $log->email }}</td>
                        <td class="px-4 py-3 text-ink-600 dark:text-ink-400">
                            <p class="max-w-xs truncate" title="{{ $log->message }}">{{ $log->message }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ match ($log->status) {
                                'sent' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
                                'failed' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
                                default => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
                            } }}">{{ str($log->status)->headline() }}</span>
                        </td>
                        <td class="px-4 py-3 text-ink-500 dark:text-ink-400">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <details class="group">
                                    <summary class="cursor-pointer list-none rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-700 hover:border-ink-400 dark:border-ink-700 dark:text-ink-300">Edit</summary>
                                    <form method="POST" action="{{ route('admin.settings.emails.update', $log) }}" class="mt-3 grid grid-cols-1 gap-2 rounded-xl border border-ink-100 p-3 dark:border-ink-800">
                                        @csrf
                                        @method('PUT')
                                        <input name="email" type="email" value="{{ $log->email }}" required
                                            class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                        <select name="status" required
                                            class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                                            @foreach (['pending', 'sent', 'failed'] as $value)
                                                <option value="{{ $value }}" @selected($log->status === $value)>{{ str($value)->headline() }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="message" rows="2" required
                                            class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">{{ $log->message }}</textarea>
                                        <button type="submit" class="w-fit rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950">Save</button>
                                    </form>
                                </details>

                                <form method="POST" action="{{ route('admin.settings.emails.destroy', $log) }}" onsubmit="return confirm('Delete this email log entry?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-semibold text-ink-500 hover:border-rose-400 hover:text-rose-600 dark:border-ink-700 dark:text-ink-400">
                                        <i class="ph ph-trash text-sm"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-ink-500 dark:text-ink-400">No emails logged yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $emails->links() }}</div>

</x-layouts.admin>
