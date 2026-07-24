import { startPolling } from './poll';

export default function bookingCalendar({ courtId, slotsUrl, periodBoundaries, periodEnds }) {
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
    const ends = periodEnds || { morning: boundaries.afternoon, afternoon: boundaries.evening, evening: '00:00' };
    const orderedKeys = Object.keys(boundaries).sort((a, b) => toMinutes(boundaries[a]) - toMinutes(boundaries[b]));

    const periodForMinutes = (minutes) => {
        let match = null;
        for (const key of orderedKeys) {
            if (toMinutes(boundaries[key]) <= minutes) {
                match = key;
            } else {
                break;
            }
        }

        if (!match) {
            // Nothing matched - this time is before the first configured
            // boundary (e.g. 6am when Morning starts at 11am). Only call it
            // spillover from the last period (Evening) if that period
            // actually wraps past midnight and this time falls within the
            // wrapped range; otherwise it's just early/before opening, so
            // show it with the first period instead of mislabeling it.
            const lastKey = orderedKeys[orderedKeys.length - 1];
            const lastEnd = toMinutes(ends[lastKey]);
            const wrapsPastMidnight = lastEnd <= toMinutes(boundaries[lastKey]);
            match = wrapsPastMidnight && minutes < lastEnd ? lastKey : orderedKeys[0];
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
        // Picks persist across date switches, keyed by slot id, so a
        // customer can pick times on the 24th, browse to the 25th, and pick
        // more there without losing the 24th's picks - they're building one
        // multi-date booking, not restarting per date. Each entry carries
        // its own slot_date since the /slots endpoint doesn't include it
        // (it's implied by the date that was requested).
        pickedSlots: {},
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
                    hasPick: Object.values(this.pickedSlots).some((s) => s.slot_date === dateStr),
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
            // Intentionally not touching pickedSlots here - switching dates
            // is just browsing to a different day's availability, not
            // discarding what was already picked on other days.
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
        // visitor already picked just got taken, drop just that pick and
        // warn instead of letting them submit a booking that's guaranteed
        // to fail. Only reconciles picks on the date currently being viewed
        // - this fetch doesn't know about other dates' availability, so
        // picks made there must be left alone.
        async refreshSlots() {
            if (!this.selectedDateStr || this.loading) return;

            try {
                const slots = await this.loadSlots(this.selectedDateStr);
                if (slots === null) return;

                const stillOpenIds = new Set(slots.map((s) => s.id));
                const lostIds = Object.values(this.pickedSlots)
                    .filter((s) => s.slot_date === this.selectedDateStr && !stillOpenIds.has(s.id))
                    .map((s) => s.id);

                this.slots = slots;

                if (lostIds.length) {
                    lostIds.forEach((id) => delete this.pickedSlots[id]);
                    this.showQuickDetails = false;
                    this.showReserveSheet = false;
                    this.warning = 'One or more of your selected slots were just booked by someone else. Please pick again.';
                }
            } catch (e) {
                // Transient network hiccup - don't disrupt the page over it.
            }
        },

        // Independent checkbox-style selection: clicking any slot toggles
        // just that slot, with no auto-fill of the range between two
        // clicks. Non-contiguous picks are valid - each contiguous run
        // becomes its own booking under one combined payment, see
        // BookingOrderService::checkout() / selectedGroups() below.
        pickSlot(index) {
            this.warning = null;
            const slot = this.slots[index];
            if (!slot) return;

            if (this.pickedSlots[slot.id]) {
                delete this.pickedSlots[slot.id];
            } else {
                this.pickedSlots[slot.id] = { ...slot, slot_date: this.selectedDateStr };
            }
        },

        // Client-side mirror of BookingService::groupContiguousSlotIds() -
        // for an accurate "N booking(s)" preview before submit only; the
        // server re-derives this itself as the authoritative version. Also
        // splits on a date change, since two picks on different dates must
        // never merge into one run even if their clock times line up.
        get selectedGroups() {
            const groups = [];

            for (const slot of this.selectedSlots) {
                const last = groups[groups.length - 1];
                const lastSlot = last ? last[last.length - 1] : null;

                if (lastSlot && lastSlot.slot_date === slot.slot_date && lastSlot.end_time === slot.start_time) {
                    last.push(slot);
                } else {
                    groups.push([slot]);
                }
            }

            return groups;
        },

        isSelected(index) {
            const slot = this.slots[index];
            return !!slot && !!this.pickedSlots[slot.id];
        },

        get groupedSlots() {
            const groups = [];

            this.slots.forEach((slot, index) => {
                const [h, m] = slot.start_time.split(':').map(Number);
                const info = periodForMinutes(h * 60 + m);
                let group = groups.find((g) => g.key === info.key);

                if (!group) {
                    group = { key: info.key, label: info.label, icon: info.icon, color: info.color, items: [] };
                    groups.push(group);
                }

                group.items.push({ slot, index });
            });

            // Groups are otherwise built in whatever order slots are first
            // encountered - since anything before the earliest boundary
            // (e.g. a slot at 6am when Morning starts at 11am) falls back to
            // the last period (Evening, spilling over from the previous
            // night), that group could get created first and display above
            // Morning/Afternoon. Always show them in the admin-configured
            // boundary order instead.
            return groups.sort((a, b) => orderedKeys.indexOf(a.key) - orderedKeys.indexOf(b.key));
        },

        // Sorted across every date the customer has picked on, not just the
        // one currently being viewed.
        get selectedSlots() {
            return Object.values(this.pickedSlots).sort((a, b) => {
                if (a.slot_date !== b.slot_date) return a.slot_date < b.slot_date ? -1 : 1;
                return a.start_time < b.start_time ? -1 : 1;
            });
        },

        get selectedSlotIds() {
            return this.selectedSlots.map((s) => s.id);
        },

        get totalPrice() {
            return this.selectedSlots.reduce((sum, s) => sum + parseFloat(s.price), 0);
        },

        // Formats an arbitrary date string for display - used per group
        // now that a booking can span more than one date.
        dateLabelFor(dateStr) {
            const [y, m, d] = dateStr.split('-').map(Number);
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
            this.pickedSlots = {};
            this.showQuickDetails = false;
            this.showReserveSheet = false;
        },
    };
}
