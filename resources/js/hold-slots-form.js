// Minimal Alpine component for the "put hours on hold" page - reuses just
// the affected-hours-picker piece of admin-booking-form.js, without any of
// the destination-court/date/slot-picking state that page needs but this
// one doesn't (there's no destination to pick for a hold).
export default function holdSlotsForm({ bookingSlots = [] }) {
    return {
        allBookingSlots: bookingSlots,
        affected: {},

        toggleAffected(id) {
            let next = { ...this.affected };
            if (next[id]) { delete next[id]; } else { next[id] = true; }

            // bookingSlots is one contiguous run, so a contiguous run of
            // INDICES into it is equivalent to a contiguous run of time -
            // restart the selection with just this slot if it would break
            // that (same idea as admin-booking-form.js's staysContiguous()).
            const indices = this.allBookingSlots.map((s, i) => (next[s.id] ? i : null)).filter((i) => i !== null);
            const isContiguous = indices.length === 0 || (indices[indices.length - 1] - indices[0] + 1) === indices.length;

            this.affected = isContiguous ? next : { [id]: true };
        },

        get affectedIds() {
            return Object.keys(this.affected).map(Number);
        },

        get isPartial() {
            return this.affectedIds.length > 0 && this.affectedIds.length < this.allBookingSlots.length;
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
    };
}
