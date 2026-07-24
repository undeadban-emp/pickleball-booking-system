@php
    $isHost = auth()->check() && $room->host_user_id === auth()->id();
    $myPlayer = auth()->check() ? $room->players->firstWhere('user_id', auth()->id()) : null;
    $hasJoined = $myPlayer !== null;
    $isCheckedIn = $myPlayer?->isCheckedIn() ?? false;
@endphp

<x-layouts.app :title="$room->title" :hide-footer="true">

    <section class="mx-auto max-w-2xl px-4 py-14 sm:px-6 lg:px-8">
        <a href="{{ route('open-play.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition-colors hover:text-ink-800 dark:text-ink-400 dark:hover:text-white">
            <i class="ph ph-arrow-left"></i>
            Open Play
        </a>

        @if (session('status'))
            <div class="mt-4 rounded-xl border border-accent-300 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-800 dark:bg-accent-950 dark:text-accent-200">
                {{ session('status') }}
            </div>
        @endif

        @if ($hasJoined && $isCheckedIn)
            <div class="mt-4 flex items-center gap-2.5 rounded-xl border border-accent-300 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-800 dark:bg-accent-950 dark:text-accent-200">
                <i class="ph ph-check-circle text-lg"></i>
                <span>You're checked in! We'll seat you in a match as soon as a court is free.</span>
            </div>
        @elseif ($hasJoined && in_array($room->status, ['waiting', 'in_progress'], true))
            <div class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                <span class="flex items-center gap-2.5">
                    <i class="ph ph-map-pin-line text-lg"></i>
                    You're on the list, but not checked in yet — you won't be matched into a game until you check in.
                </span>
                <form method="POST" action="{{ route('open-play.check-in', $room) }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="rounded-full bg-amber-600 px-3.5 py-1.5 text-xs font-semibold text-white transition-transform active:scale-[0.98] hover:bg-amber-500">
                        Check in
                    </button>
                </form>
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="font-display text-3xl font-semibold tracking-tight text-ink-950 dark:text-white">
                    {{ $room->title }}
                </h1>
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                    Hosted by {{ $room->host->name }} &middot;
                    {{ \Illuminate\Support\Carbon::parse($room->session_date)->format('M j, Y') }},
                    {{ \Illuminate\Support\Carbon::parse($room->start_time)->format('g:i A') }}
                </p>
            </div>
            <x-open-play.room-status-badge :status="$room->status" class="shrink-0" />
        </div>

        <div class="mt-4 flex flex-wrap gap-2 text-sm">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-3 py-1 font-medium text-ink-700 dark:bg-ink-800 dark:text-ink-200">
                <i class="ph ph-users-three text-sm"></i>
                {{ $room->players->count() }}/{{ $room->max_players }} players
            </span>
            @if (in_array($room->status, ['waiting', 'in_progress'], true))
                <span class="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-3 py-1 font-medium text-ink-700 dark:bg-ink-800 dark:text-ink-200">
                    <i class="ph ph-map-pin-line text-sm"></i>
                    {{ $room->players->filter->isCheckedIn()->count() }} checked in
                </span>
            @endif
            <span class="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-3 py-1 font-medium text-ink-700 dark:bg-ink-800 dark:text-ink-200">
                <i class="ph ph-gauge text-sm"></i>
                {{ ucfirst($room->skill_level) }}
            </span>
            @if ($room->visibility === 'private')
                <span class="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-3 py-1 font-medium text-ink-700 dark:bg-ink-800 dark:text-ink-200">
                    <i class="ph ph-lock-simple text-sm"></i>
                    Private
                </span>
            @endif
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                <p class="text-sm font-semibold text-ink-950 dark:text-white">Courts</p>
                <ul class="mt-3 space-y-2 text-sm text-ink-600 dark:text-ink-300">
                    @foreach ($room->roomCourts as $roomCourt)
                        <li class="flex items-center gap-2">
                            <i class="ph ph-map-pin text-ink-400"></i>
                            {{ $roomCourt->court->name }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                <p class="text-sm font-semibold text-ink-950 dark:text-white">Players</p>
                <ul class="mt-3 space-y-2 text-sm text-ink-600 dark:text-ink-300">
                    @forelse ($room->players as $player)
                        @php $isYou = auth()->check() && $player->user_id === auth()->id(); @endphp
                        <li class="flex items-center gap-2 {{ $isYou ? 'font-semibold text-accent-700 dark:text-accent-400' : '' }}">
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold {{ $isYou ? 'bg-accent-500 text-white' : 'bg-ink-100 text-ink-700 dark:bg-ink-800 dark:text-ink-200' }}"
                                title="{{ $player->isCheckedIn() ? 'Checked in' : 'Not checked in yet' }}"
                            >
                                {{ strtoupper(substr($player->user->name, 0, 1)) }}
                            </span>
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $player->isCheckedIn() ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                            <span class="{{ $player->isCheckedIn() ? '' : 'text-ink-400' }}">{{ $player->user->name }}</span>
                            @if ($player->user_id === $room->host_user_id)
                                <span class="rounded-full bg-ink-100 px-1.5 py-0.5 text-[10px] font-semibold text-ink-500 dark:bg-ink-800 dark:text-ink-400">Host</span>
                            @endif
                            @if ($isYou)
                                <span class="text-xs font-normal text-accent-600 dark:text-accent-400">(you)</span>
                            @endif
                            @if ($isHost && ! $player->isCheckedIn() && ! $isYou && in_array($room->status, ['waiting', 'in_progress'], true))
                                <form method="POST" action="{{ route('open-play.players.check-in', [$room, $player]) }}" class="ml-auto">
                                    @csrf
                                    <button type="submit" class="rounded-full border border-ink-200 px-2 py-0.5 text-[10px] font-semibold text-ink-500 transition-colors hover:border-accent-400 hover:text-accent-600 dark:border-ink-700 dark:text-ink-400">
                                        Check in
                                    </button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="text-ink-400">No one has joined yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        @auth
            <div class="mt-8 flex flex-wrap items-center gap-3">
                @if ($room->status === 'waiting' && ! $isHost && ! $hasJoined)
                    <form method="POST" action="{{ route('open-play.join', $room) }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        @if ($room->visibility === 'private')
                            <input type="text" name="join_code" placeholder="Join code" required class="rounded-xl border border-ink-200 px-3 py-2 text-sm text-ink-950 placeholder:text-ink-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-white">
                        @endif
                        <button type="submit" class="rounded-full bg-accent-500 px-4 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400">Join room</button>
                    </form>
                @endif

                @if ($room->status === 'waiting' && $hasJoined && ! $isHost)
                    <form method="POST" action="{{ route('open-play.leave', $room) }}">
                        @csrf
                        <button type="submit" class="rounded-full border border-ink-200 px-4 py-2.5 text-sm font-semibold text-ink-700 transition-colors hover:border-ink-400 dark:border-ink-700 dark:text-ink-200">Leave room</button>
                    </form>
                @endif

                @if ($isHost && $room->status === 'waiting')
                    @php
                        $checkedInCount = $room->players->filter->isCheckedIn()->count();
                        $notCheckedIn = $room->players->reject->isCheckedIn();
                        $confirmStart = $checkedInCount >= 4 && $notCheckedIn->isNotEmpty();
                    @endphp
                    <form
                        method="POST"
                        action="{{ route('open-play.start', $room) }}"
                        @if ($confirmStart)
                            onsubmit="return confirmSubmit(this, {{ \Illuminate\Support\Js::from([
                                'title' => 'Start without everyone?',
                                'text' => $notCheckedIn->pluck('user.name')->implode(', ').' '.($notCheckedIn->count() === 1 ? 'hasn\'t' : 'haven\'t')." checked in yet and won't be matched until they do. Start the session anyway?",
                                'confirmButtonText' => 'Start anyway',
                            ]) }})"
                        @endif
                    >
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-4 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400">
                            <i class="ph ph-play text-base"></i>
                            Start session
                        </button>
                    </form>
                @endif

                @if ($room->status === 'in_progress')
                    <a href="{{ route('open-play.dashboard', $room) }}" class="inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-4 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-accent-400">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-white"></span>
                        View live dashboard
                    </a>
                @endif

                @if ($room->status === 'finished')
                    <a href="{{ route('open-play.summary', $room) }}" class="rounded-full border border-ink-200 px-4 py-2.5 text-sm font-semibold text-ink-700 transition-colors hover:border-ink-400 dark:border-ink-700 dark:text-ink-200">
                        View summary
                    </a>
                @endif
            </div>

            @error('join') <p class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            @error('leave') <p class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            @error('check_in') <p class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            @error('start') <p class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        @else
            <p class="mt-8 text-sm text-ink-500 dark:text-ink-400">
                <a href="{{ route('login') }}" class="font-semibold text-accent-600 dark:text-accent-400">Log in</a> to join this room.
            </p>
        @endauth
    </section>

</x-layouts.app>
