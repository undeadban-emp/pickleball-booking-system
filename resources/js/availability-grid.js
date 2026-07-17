export default function availabilityGrid({ availabilityUrl, isAuthenticated, periodBoundaries }) {
    const pad = (n) => String(n).padStart(2, '0');
    const toDateStr = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const toMinutes = (hhmm) => {
        const [h, m] = hhmm.split(':').map(Number);
        return h * 60 + (m || 0);
    };

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = toDateStr(today);

    const boundaries = periodBoundaries || { morning: '07:00', afternoon: '12:00', evening: '17:00', late: '00:00' };
    const periodMeta = {
        morning: { label: 'Morning', icon: 'ph-sun' },
        afternoon: { label: 'Afternoon', icon: 'ph-cloud-sun' },
        evening: { label: 'Evening', icon: 'ph-moon' },
        late: { label: 'Late evening', icon: 'ph-moon-stars' },
    };
    // Sort period keys by their start time, ascending. Any time before the
    // earliest boundary wraps around to belong to the LAST period in this
    // order, since that period runs past midnight into the next morning.
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
        isAuthenticated,
        dateStrip: Array.from({ length: 60 }, (_, i) => {
            const d = new Date(today);
            d.setDate(d.getDate() + i);
            return {
                dateStr: toDateStr(d),
                weekday: d.toLocaleDateString('en-US', { weekday: 'short' }),
                day: d.getDate(),
                month: d.toLocaleDateString('en-US', { month: 'short' }),
                isToday: toDateStr(d) === todayStr,
            };
        }),
        windowStart: 0,
        selectedDateStr: todayStr,
        showCalendar: false,
        calendarCursor: new Date(today.getFullYear(), today.getMonth(), 1),
        courts: [],
        times: [],
        periods: [],
        loading: false,
        error: null,

        selectedCourtId: null,
        rangeStart: null,
        rangeEnd: null,
        showReserveSheet: false,
        showQuickDetails: false,

        init() {
            this.fetchAvailability();
        },

        get selectedDateLabel() {
            const [y, m, d] = this.selectedDateStr.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
            });
        },

        selectDate(dateStr) {
            this.selectedDateStr = dateStr;
            this.clearSelection();
            this.fetchAvailability();
        },

        get visibleDates() {
            return this.dateStrip.slice(this.windowStart, this.windowStart + 7);
        },

        prevWeek() {
            this.windowStart = Math.max(0, this.windowStart - 7);
        },

        nextWeek() {
            this.windowStart = Math.min(this.dateStrip.length - 7, this.windowStart + 7);
        },

        jumpToToday() {
            this.windowStart = 0;
            this.selectDate(todayStr);
        },

        get calendarLabel() {
            return this.calendarCursor.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        },

        get calendarWeeks() {
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
                const dateStr = toDateStr(d);
                cells.push({
                    day,
                    dateStr,
                    isToday: dateStr === todayStr,
                    isSelected: dateStr === this.selectedDateStr,
                    isAvailable: validSet.has(dateStr),
                });
            }
            while (cells.length % 7 !== 0) cells.push(null);

            const weeks = [];
            for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));
            return weeks;
        },

        openCalendar() {
            const [y, m] = this.selectedDateStr.split('-').map(Number);
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

            this.windowStart = Math.min(Math.floor(index / 7) * 7, this.dateStrip.length - 7);
            this.selectDate(cell.dateStr);
            this.showCalendar = false;
        },

        async fetchAvailability() {
            this.loading = true;
            this.error = null;

            try {
                const res = await fetch(`${availabilityUrl}?date=${this.selectedDateStr}`, {
                    headers: { Accept: 'application/json' },
                });
                const body = await res.json();
                let courts = body.courts ?? [];

                // Slots that have already started (or passed entirely) today aren't bookable anymore.
                if (this.selectedDateStr === todayStr) {
                    const nowHms = new Date().toTimeString().slice(0, 8);
                    courts = courts.map((court) => ({
                        ...court,
                        slots: court.slots.filter((s) => s.start_time > nowHms),
                    }));
                }

                this.courts = courts;

                const timeSet = new Map();
                this.courts.forEach((court) => {
                    court.slots.forEach((slot) => {
                        if (!timeSet.has(slot.start_time)) {
                            timeSet.set(slot.start_time, slot.start_time);
                        }
                    });
                });
                this.times = Array.from(timeSet.keys()).sort();

                const periodMap = new Map();
                this.times.forEach((time) => {
                    const [h, m] = time.split(':').map(Number);
                    const period = periodForMinutes(h * 60 + m);
                    if (!periodMap.has(period.key)) {
                        periodMap.set(period.key, { ...period, times: [] });
                    }
                    periodMap.get(period.key).times.push(time);
                });
                const periodOrder = ['morning', 'afternoon', 'evening', 'late'];
                this.periods = Array.from(periodMap.values()).sort(
                    (a, b) => periodOrder.indexOf(a.key) - periodOrder.indexOf(b.key)
                );

                if (this.courts.every((c) => c.slots.length === 0)) {
                    this.error = 'No open slots on this date.';
                }
            } catch (e) {
                this.error = 'Could not load availability. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        slotFor(court, time) {
            return court.slots.find((s) => s.start_time === time) ?? null;
        },

        cellIndex(court, time) {
            return court.slots.findIndex((s) => s.start_time === time);
        },

        pickCell(court, time) {
            const slot = this.slotFor(court, time);
            if (!slot || slot.status !== 'available') return;

            const index = this.cellIndex(court, time);

            if (this.selectedCourtId !== court.id) {
                this.selectedCourtId = court.id;
                this.rangeStart = index;
                this.rangeEnd = index;
                return;
            }

            if (index === this.rangeStart && index === this.rangeEnd) {
                this.clearSelection();
                return;
            }

            const lo = Math.min(this.rangeStart, index);
            const hi = Math.max(this.rangeStart, index);
            const between = court.slots.slice(lo, hi + 1);
            const contiguous = between.every((s, i) => {
                if (i === 0) return true;
                if (s.status !== 'available') return false;
                return between[i - 1].end_time === s.start_time;
            });

            if (contiguous) {
                this.rangeStart = lo;
                this.rangeEnd = hi;
            } else {
                this.rangeStart = index;
                this.rangeEnd = index;
            }
        },

        isSelected(court, time) {
            if (this.selectedCourtId !== court.id || this.rangeStart === null) return false;
            const index = this.cellIndex(court, time);
            return index >= this.rangeStart && index <= this.rangeEnd;
        },

        clearSelection() {
            this.selectedCourtId = null;
            this.rangeStart = null;
            this.rangeEnd = null;
            this.showReserveSheet = false;
            this.showQuickDetails = false;
        },

        get selectedCourt() {
            return this.courts.find((c) => c.id === this.selectedCourtId) ?? null;
        },

        get selectedSlots() {
            if (!this.selectedCourt || this.rangeStart === null) return [];
            return this.selectedCourt.slots.slice(this.rangeStart, this.rangeEnd + 1);
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

        cellLabel(time, court) {
            const slot = this.slotFor(court, time);
            const end = slot ? this.formatTime(slot.end_time) : '';
            return `${this.formatTime(time)}-${end}`;
        },

        cellClass(court, time) {
            const slot = this.slotFor(court, time);
            if (!slot) return 'invisible';

            if (this.isSelected(court, time)) {
                return 'border-accent-500 bg-accent-500 text-ink-950';
            }

            switch (slot.status) {
                case 'available':
                    return 'border-sky-200 bg-sky-50 text-sky-800 hover:border-accent-400 hover:bg-accent-50 cursor-pointer dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200';
                case 'pending':
                    return 'border-amber-200 bg-amber-50 text-amber-700 cursor-not-allowed dark:border-amber-900 dark:bg-amber-950 dark:text-amber-400';
                case 'booked':
                    return 'border-rose-200 bg-rose-50 text-rose-600 line-through cursor-not-allowed dark:border-rose-900 dark:bg-rose-950 dark:text-rose-400';
                default:
                    return 'border-ink-100 bg-ink-100 text-ink-400 cursor-not-allowed dark:border-ink-800 dark:bg-ink-800 dark:text-ink-600';
            }
        },
    };
}
