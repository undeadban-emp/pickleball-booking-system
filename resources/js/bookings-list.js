import Swal from 'sweetalert2';

const STATUS_META = {
    pending_payment: {
        withReference: { label: 'Awaiting confirmation', class: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300' },
        withoutReference: { label: 'Awaiting payment', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' },
    },
    confirmed: { label: 'Confirmed', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' },
    rejected: { label: 'Payment not confirmed', class: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' },
    cancelled: { label: 'Cancelled', class: 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400' },
    completed: { label: 'Completed', class: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300' },
    no_show: { label: 'No show', class: 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400' },
};
const DEFAULT_META = { label: '', class: 'bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400' };
const POLL_INTERVAL_MS = 15000;

export default function bookingsList({ ids, statuses, hasReference, courts, pollUrl }) {
    return {
        ids,
        pollUrl,
        statuses: { ...statuses },
        hasReference: { ...hasReference },
        courts: { ...courts },
        pollTimer: null,
        visibilityHandler: null,

        init() {
            this.pollTimer = setInterval(() => this.poll(), POLL_INTERVAL_MS);

            // Background tabs throttle setInterval (sometimes to once a minute or
            // less), so a status change approved while this tab was in the
            // background could sit stale for a while. Poll immediately on return.
            this.visibilityHandler = () => {
                if (document.visibilityState === 'visible') this.poll();
            };
            document.addEventListener('visibilitychange', this.visibilityHandler);
        },

        destroy() {
            clearInterval(this.pollTimer);
            document.removeEventListener('visibilitychange', this.visibilityHandler);
        },

        async poll() {
            if (!this.ids.length) return;

            try {
                const res = await fetch(`${this.pollUrl}?ids=${this.ids.join(',')}`, {
                    headers: { Accept: 'application/json' },
                });
                const body = await res.json();
                const previousStatuses = this.statuses;

                this.statuses = { ...this.statuses, ...body.statuses };
                this.hasReference = { ...this.hasReference, ...body.has_reference };

                this.notifyDecidedBookings(previousStatuses, body.statuses ?? {});
            } catch (e) {
                // Silently skip this tick and retry on the next interval.
            }
        },

        notifyDecidedBookings(previousStatuses, newStatuses) {
            for (const id of Object.keys(newStatuses)) {
                const previous = previousStatuses[id];
                const next = newStatuses[id];

                if (previous === next) continue;
                if (next !== 'confirmed' && next !== 'rejected') continue;

                const court = this.courts[id] ?? 'your booking';

                if (next === 'confirmed') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Booking confirmed',
                        text: `${court} has been confirmed. See you on the court!`,
                        confirmButtonColor: '#16a34a',
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Booking rejected',
                        text: `${court} was not confirmed. Please check the details for more information.`,
                        confirmButtonColor: '#e11d48',
                    });
                }
            }
        },

        meta(id) {
            const status = this.statuses[id];
            const entry = STATUS_META[status];

            if (!entry) return DEFAULT_META;

            if (status === 'pending_payment') {
                return this.hasReference[id] ? entry.withReference : entry.withoutReference;
            }

            return entry;
        },

        badgeClass(id) {
            return this.meta(id).class;
        },

        badgeLabel(id) {
            return this.meta(id).label;
        },
    };
}
