import { startPolling } from './poll';
import { debounce } from './debounce';

export default function adminBookingForm({ courts, slotsUrlBase, periodBoundaries, periodEnds, initialCourtId, expectedHours, singleSession = false, bookingSlots = [] }) {
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
        courts,
        courtId: initialCourtId ?? courts[0]?.id ?? null,
        dateStr: todayStr,
        minDate: todayStr,

        // Date strip (same style as the home page booking widget): a
        // scrollable window over the next 60 days, browsed with prev/next
        // instead of a plain <input type="date">. 60 matches SlotGenerator's
        // rolling availability window (see app/Support/SlotGenerator.php),
        // so this never cuts off before real slot data does. Optional - only
        // used by pages whose markup includes the strip (currently just the
        // "New Booking" walk-in form); other adminBookingForm() consumers
        // keep their plain date input and simply never touch these.
        dateStrip: (() => {
            const days = [];
            const base = new Date();
            for (let i = 0; i < 60; i++) {
                const d = new Date(base.getFullYear(), base.getMonth(), base.getDate() + i);
                days.push({
                    dateStr: toDateStr(d),
                    weekday: d.toLocaleDateString('en-US', { weekday: 'short' }),
                    day: d.getDate(),
                    month: d.toLocaleDateString('en-US', { month: 'short' }),
                    isToday: i === 0,
                });
            }
            return days;
        })(),
        dateWindowSize: 4,
        dateWindowStart: 0,
        visibleDates() {
            return this.dateStrip.slice(this.dateWindowStart, this.dateWindowStart + this.dateWindowSize);
        },
        prevDateWindow() {
            this.dateWindowStart = Math.max(0, this.dateWindowStart - this.dateWindowSize);
        },
        nextDateWindow() {
            this.dateWindowStart = Math.min(this.dateStrip.length - this.dateWindowSize, this.dateWindowStart + this.dateWindowSize);
        },
        selectDate(dateStr) {
            this.dateStr = dateStr;
            this.onDateChange();
        },

        // Calendar popup (same as the home page booking widget) - jump to
        // any date within the bookable window instead of stepping through
        // the strip 4 days at a time.
        showCalendar: false,
        calendarCursor: new Date(),
        calendarLabel() {
            return this.calendarCursor.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        },
        calendarWeeks() {
            const year = this.calendarCursor.getFullYear();
            const month = this.calendarCursor.getMonth();
            const firstDay = new Date(year, month, 1);
            const startOffset = firstDay.getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const validSet = new Set(this.dateStrip.map((d) => d.dateStr));

            const cells = [];
            for (let i = 0; i < startOffset; i++) cells.push(null);
            for (let day = 1; day <= daysInMonth; day++) {
                const d = new Date(year, month, day);
                const ds = toDateStr(d);
                cells.push({
                    day,
                    dateStr: ds,
                    isToday: ds === todayStr,
                    isSelected: ds === this.dateStr,
                    isAvailable: validSet.has(ds),
                });
            }
            while (cells.length % 7 !== 0) cells.push(null);

            const weeks = [];
            for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));
            return weeks;
        },
        openCalendar() {
            const [y, m] = this.dateStr.split('-').map(Number);
            this.calendarCursor = new Date(y, m - 1, 1);
            this.showCalendar = true;
        },
        prevCalendarMonth() {
            this.calendarCursor = new Date(this.calendarCursor.getFullYear(), this.calendarCursor.getMonth() - 1, 1);
        },
        nextCalendarMonth() {
            this.calendarCursor = new Date(this.calendarCursor.getFullYear(), this.calendarCursor.getMonth() + 1, 1);
        },
        pickCalendarDate(cell) {
            if (!cell || !cell.isAvailable) return;

            const index = this.dateStrip.findIndex((d) => d.dateStr === cell.dateStr);
            if (index === -1) return;

            this.dateWindowStart = Math.min(Math.floor(index / this.dateWindowSize) * this.dateWindowSize, this.dateStrip.length - this.dateWindowSize);
            this.selectDate(cell.dateStr);
            this.showCalendar = false;
        },
        // Whether any currently-picked slot (any court, any of the picked
        // dates) falls on this date - drives the small dot shown on
        // strip/calendar cells so a date with existing picks stands out.
        hasPickOn(dateStr) {
            return Object.values(this.pickedSlots).some((s) => s.slot_date === dateStr);
        },

        slots: [],
        // Picks persist across date switches, keyed by slot id, so staff
        // can pick sessions on one date, change the date to add another
        // session, and not lose what was already picked - one walk-in
        // booking can cover several dates. Cleared on a court change
        // instead, since the booking is submitted against a single court id
        // shared by every picked slot. Each entry carries its own
        // slot_date since the /slots endpoint doesn't include it (it's
        // implied by the date that was requested).
        pickedSlots: {},
        loading: false,
        error: null,
        warning: null,

        // Partial-reschedule support (reschedule.blade.php only, bookingSlots
        // is the booking's own current slots): which of THIS booking's
        // existing hours are affected/being moved, as opposed to pickedSlots
        // above which is the destination this affected subset is moving to.
        // Left empty (or fully selected), the whole booking moves as before.
        allBookingSlots: bookingSlots,
        affected: {},
        toggleAffected(id) {
            let next = { ...this.affected };
            if (next[id]) { delete next[id]; } else { next[id] = true; }

            // bookingSlots is already one contiguous run, so a contiguous
            // run of INDICES into it is equivalent to a contiguous run of
            // time - restart the selection with just this slot if it would
            // break that, same idea as staysContiguous() below.
            const indices = this.allBookingSlots.map((s, i) => (next[s.id] ? i : null)).filter((i) => i !== null);
            const isContiguous = indices.length === 0 || (indices[indices.length - 1] - indices[0] + 1) === indices.length;

            this.affected = isContiguous ? next : { [id]: true };

            const count = Object.keys(this.affected).length;
            this.expectedHours = (count > 0 && count < this.allBookingSlots.length) ? count : (this.allBookingSlots.length || expectedHours);
        },
        get affectedIds() {
            return Object.keys(this.affected).map(Number);
        },
        get isPartial() {
            return this.affectedIds.length > 0 && this.affectedIds.length < this.allBookingSlots.length;
        },
        expectedHours: expectedHours || null,

        init() {
            if (this.courtId) this.fetchSlots();

            // Debounces user-triggered date/court switches (see
            // onDateChange()/onCourtChange() below) so rapidly tapping
            // through several dates or courts fires one request for the
            // final pick instead of one per tap - avoids tripping the
            // availability-read rate limit. The initial load above stays
            // immediate/undebounced.
            this._debouncedFetch = debounce(() => this.fetchSlots(), 350);

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

        // A different court means every previously picked slot would be
        // submitted under the wrong court id (the form posts one court_id
        // for the whole booking), so the selection can't survive this.
        onCourtChange() {
            this.clearSelection();
            this.loading = true;
            this._debouncedFetch();
        },

        // Just browsing to a different day's availability for the same
        // court - keep whatever was already picked on other dates.
        onDateChange() {
            this.loading = true;
            this._debouncedFetch();
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
        // already picked just got taken, drop just that pick and warn
        // instead of letting them submit a booking that's guaranteed to
        // fail. Only reconciles picks for the court/date currently being
        // viewed - this fetch doesn't know about other dates' availability.
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
                const lostIds = Object.values(this.pickedSlots)
                    .filter((s) => s.slot_date === this.dateStr && !stillOpenIds.has(s.id))
                    .map((s) => s.id);

                this.slots = slots;

                if (lostIds.length) {
                    lostIds.forEach((id) => delete this.pickedSlots[id]);
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
        //
        // Independent checkbox-style selection: clicking any slot toggles
        // just that slot, no auto-fill of the range between two clicks.
        // Non-contiguous picks are valid - each contiguous run becomes its
        // own confirmed booking, see selectedGroups() below. Exception:
        // singleSession mode (the reschedule page) can only ever submit one
        // contiguous block, so picking a slot that would create a second,
        // disconnected block starts a fresh selection with just that slot
        // instead of silently building an invalid pick the submit button
        // would just reject anyway.
        pickSlot(index) {
            this.warning = null;
            const slot = this.slots[index];
            if (!slot) return;

            let newPicked = { ...this.pickedSlots };

            if (newPicked[slot.id]) {
                delete newPicked[slot.id];
            } else if (this.singleSession && !this.staysContiguous(newPicked, slot)) {
                newPicked = { [slot.id]: { ...slot, slot_date: this.dateStr } };
            } else {
                newPicked[slot.id] = { ...slot, slot_date: this.dateStr };
            }

            this.applySelection(newPicked);
        },

        // Whether adding `slot` to `picked` would still leave everything as
        // one contiguous run (same date, back-to-back times) - used only in
        // singleSession mode, see pickSlot() above.
        staysContiguous(picked, slot) {
            const list = [...Object.values(picked), { ...slot, slot_date: this.dateStr }].sort((a, b) => {
                if (a.slot_date !== b.slot_date) return a.slot_date < b.slot_date ? -1 : 1;
                return a.start_time < b.start_time ? -1 : 1;
            });

            for (let i = 1; i < list.length; i++) {
                const prev = list[i - 1];
                const cur = list[i];
                if (prev.slot_date !== cur.slot_date || prev.end_time !== cur.start_time) {
                    return false;
                }
            }

            return true;
        },

        // Client-side mirror of BookingService::groupContiguousSlotIds() -
        // preview only, the server re-derives this as the authoritative
        // version. Also splits on a date change, since two picks on
        // different dates must never merge into one run even if their
        // clock times line up.
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

        // On a reschedule, going past the customer's original hour count isn't
        // wrong, just worth a second look before it's confirmed - the
        // selection only applies if the admin explicitly says to extend it.
        applySelection(newPicked) {
            const count = Object.keys(newPicked).length;

            if (this.expectedHours && count > this.expectedHours && window.Swal) {
                window.Swal.fire({
                    title: 'Extend beyond original booking?',
                    text: `This customer originally booked ${this.expectedHours} hour${this.expectedHours === 1 ? '' : 's'}. You're about to select ${count} hours instead. Continue?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, extend it',
                    confirmButtonColor: '#111827',
                    cancelButtonText: 'Cancel',
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.pickedSlots = newPicked;
                    }
                });
                return;
            }

            this.pickedSlots = newPicked;
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
                    group = { key: info.key, label: info.label, icon: info.icon, items: [] };
                    groups.push(group);
                }

                group.items.push({ slot, index });
            });

            const order = orderedKeys;
            groups.sort((a, b) => order.indexOf(a.key) - order.indexOf(b.key));

            return groups;
        },

        // Sorted across every date that's been picked, not just the one
        // currently being viewed.
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

        clearSelection() {
            this.pickedSlots = {};
        },
    };
}
