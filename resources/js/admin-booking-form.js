import { startPolling } from './poll';

export default function adminBookingForm({ courts, slotsUrlBase, periodBoundaries, initialCourtId, expectedHours }) {
    const pad = (n) => String(n).padStart(2, '0');
    const toDateStr = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const todayStr = toDateStr(new Date());

    const toMinutes = (hhmm) => {
        const [h, m] = hhmm.split(':').map(Number);
        return h * 60 + (m || 0);
    };

    // Same grouping the home page availability grid uses, driven by the
    // admin-configured Time-of-day Groups boundaries (not hardcoded hours).
    const boundaries = periodBoundaries || { morning: '07:00', afternoon: '12:00', evening: '17:00' };
    const periodMeta = {
        morning: { label: 'Morning', icon: 'ph-sun' },
        afternoon: { label: 'Afternoon', icon: 'ph-cloud-sun' },
        evening: { label: 'Evening', icon: 'ph-moon' },
    };
    const orderedKeys = Object.keys(boundaries).sort((a, b) => toMinutes(boundaries[a]) - toMinutes(boundaries[b]));

    const periodForMinutes = (minutes) => {
        let match = orderedKeys[orderedKeys.length - 1];
        for (const key of orderedKeys) {
            if (toMinutes(boundaries[key]) <= minutes) {
                match = key;
            } else {
                break;
            }
        }
        return { key: match, ...periodMeta[match] };
    };

    return {
        courts,
        courtId: initialCourtId ?? courts[0]?.id ?? null,
        dateStr: todayStr,
        minDate: todayStr,
        slots: [],
        selectedIds: [],
        loading: false,
        error: null,
        warning: null,
        expectedHours: expectedHours || null,

        init() {
            if (this.courtId) this.fetchSlots();

            // Background poll so a slot someone else just booked disappears
            // from the list (and gets kicked out of the current selection)
            // without the admin having to manually refresh the page. Pauses
            // while the tab is hidden so a backgrounded tab doesn't keep
            // polling for nothing.
            startPolling(() => this.refreshSlots(), 7000);
        },

        get selectedCourt() {
            return this.courts.find((c) => c.id === Number(this.courtId)) ?? null;
        },

        onCourtOrDateChange() {
            this.clearSelection();
            this.fetchSlots();
        },

        applyFilters(slots) {
            // Slots that already started (or passed) today can't be booked either.
            if (this.dateStr === todayStr) {
                const nowHms = new Date().toTimeString().slice(0, 8);
                return slots.filter((s) => s.start_time > nowHms);
            }

            return slots;
        },

        // Foreground fetch: initial load or court/date change. Shows the
        // loading state and surfaces errors (e.g. maintenance) directly.
        async fetchSlots() {
            if (!this.courtId || !this.dateStr) return;

            this.loading = true;
            this.error = null;
            this.warning = null;
            this.slots = [];

            try {
                const res = await fetch(`${slotsUrlBase}/${this.courtId}/slots?date=${this.dateStr}`, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (res.status === 423) {
                    const body = await res.json();
                    this.error = body.message ?? 'This court is under maintenance.';
                    return;
                }

                const body = await res.json();
                this.slots = this.applyFilters(body.data ?? []);

                if (this.slots.length === 0) {
                    this.error = 'No open slots on this date.';
                }
            } catch (e) {
                this.error = 'Could not load availability. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        // Background refresh: silent, no loading spinner. If a slot the admin
        // already picked just got taken, drop the selection and warn instead
        // of letting them submit a booking that's guaranteed to fail.
        async refreshSlots() {
            if (!this.courtId || !this.dateStr || this.loading) return;

            try {
                const res = await fetch(`${slotsUrlBase}/${this.courtId}/slots?date=${this.dateStr}`, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!res.ok) return;

                const body = await res.json();
                const slots = this.applyFilters(body.data ?? []);
                const stillOpenIds = new Set(slots.map((s) => s.id));
                const lostSelection = this.selectedIds.some((id) => !stillOpenIds.has(id));

                this.slots = slots;

                if (lostSelection) {
                    this.selectedIds = [];
                    this.warning = 'One or more of your selected slots were just booked by someone else. Please pick again.';
                }
            } catch (e) {
                // Transient network hiccup - don't disrupt the form over it.
            }
        },

        // Only truly available slots ever reach this.slots (the API already
        // filters to status=available), so every clickable cell here is
        // bookable - the server re-checks under a row lock on submit too, in
        // case someone else grabs the same slot in the meantime.
        pickSlot(index) {
            this.warning = null;
            const slot = this.slots[index];
            if (!slot) return;

            if (this.selectedIds.length === 0) {
                this.applySelection([slot.id]);
                return;
            }

            if (this.selectedIds.length === 1 && this.selectedIds[0] === slot.id) {
                this.clearSelection();
                return;
            }

            const firstIndex = this.slots.findIndex((s) => s.id === this.selectedIds[0]);
            const lo = Math.min(firstIndex, index);
            const hi = Math.max(firstIndex, index);
            const between = this.slots.slice(lo, hi + 1);
            const contiguous = between.every((s, i) => {
                if (i === 0) return true;
                return between[i - 1].end_time === s.start_time;
            });

            this.applySelection(contiguous ? between.map((s) => s.id) : [slot.id]);
        },

        // On a rebook, going past the customer's original hour count isn't
        // wrong, just worth a second look before it's confirmed - the
        // selection only applies if the admin explicitly says to extend it.
        applySelection(newIds) {
            if (this.expectedHours && newIds.length > this.expectedHours && window.Swal) {
                window.Swal.fire({
                    title: 'Extend beyond original booking?',
                    text: `This customer originally booked ${this.expectedHours} hour${this.expectedHours === 1 ? '' : 's'}. You're about to select ${newIds.length} hours instead. Continue?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, extend it',
                    confirmButtonColor: '#111827',
                    cancelButtonText: 'Cancel',
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.selectedIds = newIds;
                    }
                });
                return;
            }

            this.selectedIds = newIds;
        },

        isSelected(index) {
            const slot = this.slots[index];
            return !!slot && this.selectedIds.includes(slot.id);
        },

        get groupedSlots() {
            const groups = [];

            this.slots.forEach((slot, index) => {
                const [h, m] = slot.start_time.split(':').map(Number);
                const info = periodForMinutes(h * 60 + m);
                let group = groups.find((g) => g.key === info.key);

                if (!group) {
                    group = { key: info.key, label: info.label, icon: info.icon, items: [] };
                    groups.push(group);
                }

                group.items.push({ slot, index });
            });

            const order = orderedKeys;
            groups.sort((a, b) => order.indexOf(a.key) - order.indexOf(b.key));

            return groups;
        },

        get selectedSlots() {
            return this.slots.filter((s) => this.selectedIds.includes(s.id));
        },

        get selectedSlotIds() {
            return this.selectedIds;
        },

        get totalPrice() {
            return this.selectedSlots.reduce((sum, s) => sum + parseFloat(s.price), 0);
        },

        formatTime(t) {
            const [h, m] = t.split(':');
            const hour = parseInt(h, 10);
            const suffix = hour >= 12 ? 'pm' : 'am';
            const displayHour = hour % 12 === 0 ? 12 : hour % 12;
            return m === '00' ? `${displayHour}${suffix}` : `${displayHour}:${m}${suffix}`;
        },

        slotLabel(slot) {
            return `${this.formatTime(slot.start_time)}-${this.formatTime(slot.end_time)}`;
        },

        clearSelection() {
            this.selectedIds = [];
        },
    };
}
