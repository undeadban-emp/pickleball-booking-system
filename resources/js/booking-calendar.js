import { startPolling } from './poll';

export default function bookingCalendar({ courtId, slotsUrl, periodBoundaries }) {
    const pad = (n) => String(n).padStart(2, '0');
    const toDateStr = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const toMinutes = (hhmm) => {
        const [h, m] = hhmm.split(':').map(Number);
        return h * 60 + (m || 0);
    };

    const now = new Date();
    const todayStr = toDateStr(now);

    // Same grouping the home page availability grid uses, driven by the
    // admin-configured Time-of-day Groups boundaries (not hardcoded hours).
    const boundaries = periodBoundaries || { morning: '07:00', afternoon: '12:00', evening: '17:00' };
    const periodMeta = {
        morning: { label: 'Morning', icon: 'ph-sun-horizon', color: 'text-amber-600 dark:text-amber-400' },
        afternoon: { label: 'Afternoon', icon: 'ph-sun', color: 'text-orange-600 dark:text-orange-400' },
        evening: { label: 'Evening', icon: 'ph-sunset', color: 'text-rose-600 dark:text-rose-400' },
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
        courtId,
        slotsUrl,
        viewYear: now.getFullYear(),
        viewMonth: now.getMonth(), // 0-indexed
        selectedDateStr: null,
        slots: [],
        selectedIds: [],
        loading: false,
        error: null,
        warning: null,
        showQuickDetails: false,
        showReserveSheet: false,

        init() {
            // Background poll so a slot someone else just booked disappears
            // from the list (and gets kicked out of the current selection)
            // without needing a manual page refresh. Pauses while the tab is
            // hidden so a backgrounded tab doesn't keep polling for nothing.
            startPolling(() => this.refreshSlots(), 7000);
        },

        get monthLabel() {
            return new Date(this.viewYear, this.viewMonth, 1).toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric',
            });
        },

        get calendarDays() {
            const firstOfMonth = new Date(this.viewYear, this.viewMonth, 1);
            const startOffset = firstOfMonth.getDay(); // 0 = Sunday
            const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();

            const days = [];

            for (let i = 0; i < startOffset; i++) {
                days.push(null);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(this.viewYear, this.viewMonth, day);
                const dateStr = toDateStr(date);
                days.push({
                    day,
                    dateStr,
                    isPast: dateStr < todayStr,
                    isToday: dateStr === todayStr,
                });
            }

            return days;
        },

        prevMonth() {
            this.viewMonth -= 1;
            if (this.viewMonth < 0) {
                this.viewMonth = 11;
                this.viewYear -= 1;
            }
        },

        nextMonth() {
            this.viewMonth += 1;
            if (this.viewMonth > 11) {
                this.viewMonth = 0;
                this.viewYear += 1;
            }
        },

        async selectDate(dateStr, isPast) {
            if (isPast) return;

            this.selectedDateStr = dateStr;
            this.selectedIds = [];
            this.error = null;
            this.warning = null;
            this.loading = true;
            this.showQuickDetails = false;
            this.showReserveSheet = false;

            try {
                const slots = await this.loadSlots(dateStr);
                if (slots === null) return; // maintenance - error already set

                this.slots = slots;
                if (this.slots.length === 0) {
                    this.error = 'No open slots on this date.';
                }
            } catch (e) {
                this.error = 'Could not load availability. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        // Shared fetch for both the initial/date-change load and the
        // background poll. Returns null (with this.error set) on maintenance.
        async loadSlots(dateStr) {
            const res = await fetch(`${this.slotsUrl}?date=${dateStr}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });

            if (res.status === 423) {
                const body = await res.json();
                this.error = body.message ?? 'This court is under maintenance.';
                return null;
            }

            const body = await res.json();
            let slots = body.data ?? [];

            // Slots that have already started (or passed entirely) today aren't bookable anymore.
            if (dateStr === todayStr) {
                const nowHms = new Date().toTimeString().slice(0, 8);
                slots = slots.filter((s) => s.start_time > nowHms);
            }

            return slots;
        },

        // Background refresh: silent, no loading spinner. If a slot this
        // visitor already picked just got taken, drop the selection and warn
        // instead of letting them submit a booking that's guaranteed to fail.
        async refreshSlots() {
            if (!this.selectedDateStr || this.loading) return;

            try {
                const slots = await this.loadSlots(this.selectedDateStr);
                if (slots === null) return;

                const stillOpenIds = new Set(slots.map((s) => s.id));
                const lostSelection = this.selectedIds.some((id) => !stillOpenIds.has(id));

                this.slots = slots;

                if (lostSelection) {
                    this.selectedIds = [];
                    this.showQuickDetails = false;
                    this.showReserveSheet = false;
                    this.warning = 'One or more of your selected slots were just booked by someone else. Please pick again.';
                }
            } catch (e) {
                // Transient network hiccup - don't disrupt the page over it.
            }
        },

        pickSlot(index) {
            this.warning = null;
            const slot = this.slots[index];
            if (!slot) return;

            if (this.selectedIds.length === 0) {
                this.selectedIds = [slot.id];
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

            this.selectedIds = contiguous ? between.map((s) => s.id) : [slot.id];
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
                let group = groups.find((g) => g.label === info.label);

                if (!group) {
                    group = { label: info.label, icon: info.icon, color: info.color, items: [] };
                    groups.push(group);
                }

                group.items.push({ slot, index });
            });

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

        get selectedDateLabel() {
            if (!this.selectedDateStr) return '';

            const [y, m, d] = this.selectedDateStr.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
            });
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
            this.showQuickDetails = false;
            this.showReserveSheet = false;
        },
    };
}
