<x-layouts.admin :title="'Users'">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Users</h1>
        <details class="group">
            <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                <i class="ph ph-user-plus text-base"></i>
                Register client
            </summary>
            <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 grid grid-cols-1 gap-3 rounded-2xl border border-ink-200 bg-white p-4 sm:grid-cols-2 dark:border-ink-800 dark:bg-ink-900">
                @csrf
                <input name="name" type="text" placeholder="Full name" value="{{ old('name') }}" required
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input name="phone" type="text" placeholder="Phone (optional)" value="{{ old('phone') }}"
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input name="email" type="email" placeholder="Email" value="{{ old('email') }}" required
                    class="col-span-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input name="password" type="password" placeholder="Password" required
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <input name="password_confirmation" type="password" placeholder="Confirm password" required
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100">
                <button type="submit" class="col-span-full rounded-lg bg-accent-500 px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-accent-400">Register</button>
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

    {{-- Filters --}}
    <form method="GET" class="mt-6 flex flex-wrap items-end gap-3 rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Role</label>
            <select name="role" class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-800 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-200">
                <option value="">All</option>
                @foreach (['admin' => 'Admin', 'staff' => 'Staff', 'customer' => 'Customer'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-1 min-w-[180px] flex-col gap-1">
            <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Search</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, email, or phone"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-800 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-200">
        </div>

        <button type="submit" class="rounded-lg bg-ink-950 px-4 py-2 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
            Filter
        </button>
        @if (array_filter($filters))
            <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">Clear</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="mt-4 overflow-x-auto rounded-2xl border border-ink-200 dark:border-ink-800">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-ink-100 text-xs font-medium tracking-wide text-ink-500 uppercase dark:bg-ink-800 dark:text-ink-400">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Contact</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Bookings</th>
                    <th class="px-4 py-3">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                @forelse ($users as $user)
                    <tr class="hover:bg-ink-50 dark:hover:bg-ink-800/50">
                        <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-100">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-ink-600 dark:text-ink-400">
                            <p>{{ $user->email }}</p>
                            @if ($user->phone)
                                <p class="text-xs text-ink-400">{{ $user->phone }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ match (true) {
                                $user->isAdmin() => 'bg-accent-100 text-accent-800 dark:bg-accent-950 dark:text-accent-300',
                                $user->isStaff() => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
                                default => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
                            } }}">{{ str($user->role)->headline() }}</span>
                        </td>
                        <td class="px-4 py-3 text-ink-600 dark:text-ink-400">{{ $user->bookings_count }}</td>
                        <td class="px-4 py-3 text-ink-500 dark:text-ink-400">{{ $user->created_at->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-ink-500 dark:text-ink-400">No users match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>

</x-layouts.admin>
