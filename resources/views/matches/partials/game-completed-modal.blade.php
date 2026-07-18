{{-- Expects to be rendered inside the matchScoreboard Alpine component scope. --}}
<div
    x-show="pendingWinnerTeam !== null"
    x-cloak
    x-transition.opacity
    class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/60 px-4"
>
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl dark:bg-ink-900">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-accent-100 dark:bg-accent-950">
            <i class="ph ph-trophy text-2xl text-accent-700 dark:text-accent-400"></i>
        </span>

        <p class="mt-4 font-display text-lg font-semibold text-ink-950 dark:text-white">
            Game <span x-text="game.game_number"></span> complete
        </p>
        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
            <span x-text="teamName(pendingWinnerTeam)"></span> won <span x-text="`${game.team1_score} - ${game.team2_score}`"></span>
        </p>

        <button
            type="button"
            @click="confirmGameComplete()"
            :disabled="loading"
            class="mt-5 w-full rounded-full bg-ink-950 px-6 py-3 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-ink-800 disabled:opacity-60 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400"
        >
            Confirmed
        </button>
    </div>
</div>
