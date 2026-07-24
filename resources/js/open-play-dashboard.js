import { startPolling } from './poll';

export default function openPlayDashboard({ pollUrl, completeUrlTemplate, isHost }) {
    return {
        isHost,
        state: { room: null, courts: [], waiting: [] },

        init() {
            this.refresh();
            startPolling(() => this.refresh(), 6000);
        },

        async refresh() {
            try {
                const res = await fetch(this.pollUrl(), {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!res.ok) return;

                this.state = await res.json();
            } catch (e) {
                // Transient network hiccup - don't disrupt the view over it.
            }
        },

        pollUrl() {
            return pollUrl;
        },

        rankClass(rank) {
            const classes = {
                Advanced: 'bg-accent-100 text-accent-800 dark:bg-accent-950 dark:text-accent-300',
                Intermediate: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300',
                Beginner: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            };

            return classes[rank] ?? 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400';
        },

        async completeMatch(matchId, winnerTeam) {
            const url = completeUrlTemplate.replace('__MATCH__', matchId);
            const token = document.querySelector('meta[name="csrf-token"]')?.content;

            await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ winner_team: winnerTeam }),
            });

            this.refresh();
        },
    };
}
