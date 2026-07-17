export default function bookingCalendar({ courtId, slotsUrl }) {
    const pad = (n) => String(n).padStart(2, '0');
    const toDateStr = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    const now = new Date();
    const todayStr = toDateStr(now);

    return {
        courtId,
        slotsUrl,
        viewYear: now.getFullYear(),
        viewMonth: now.getMonth(), // 0-indexed
        selectedDateStr: null,
        slots: [],
        rangeStart: null,
        rangeEnd: null,
        loading: false,
        error: null,

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
            this.rangeStart = null;
            this.rangeEnd = null;
            this.error = null;
            this.loading = true;

            try {
                const res = await fetch(`${this.slotsUrl}?date=${dateStr}`, {
                    headers: { Accept: 'application/json' },
                });

                if (res.status === 423) {
                    this.slots = [];
                    const body = await res.json();
                    this.error = body.message ?? 'This court is under maintenance.';
                } else {
                    const body = await res.json();
                    let slots = body.data ?? [];

                    // Slots that have already started (or passed entirely) today aren't bookable anymore.
                    if (dateStr === todayStr) {
                        const nowHms = new Date().toTimeString().slice(0, 8);
                        slots = slots.filter((s) => s.start_time > nowHms);
                    }

                    this.slots = slots;
                    if (this.slots.length === 0) {
                        this.error = 'No open slots on this date.';
                    }
                }
            } catch (e) {
                this.error = 'Could not load availability. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        pickSlot(index) {
            if (this.rangeStart === null) {
                this.rangeStart = index;
                this.rangeEnd = index;
                return;
            }

            if (index === this.rangeStart && index === this.rangeEnd) {
                this.rangeStart = null;
                this.rangeEnd = null;
                return;
            }

            const lo = Math.min(this.rangeStart, index);
            const hi = Math.max(this.rangeStart, index);
            const between = this.slots.slice(lo, hi + 1);
            const contiguous = between.every((s, i) => {
                if (i === 0) return true;
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

        isSelected(index) {
            return this.rangeStart !== null && index >= this.rangeStart && index <= this.rangeEnd;
        },

        get selectedSlots() {
            if (this.rangeStart === null) return [];
            return this.slots.slice(this.rangeStart, this.rangeEnd + 1);
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
            const suffix = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 === 0 ? 12 : hour % 12;
            return `${displayHour}:${m} ${suffix}`;
        },

        clearSelection() {
            this.rangeStart = null;
            this.rangeEnd = null;
        },
    };
}
