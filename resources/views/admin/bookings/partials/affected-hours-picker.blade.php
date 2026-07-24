@php
    $verb = $verb ?? 'move';
@endphp

@if ($booking->slots->count() > 1)
    <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
        <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-ink-400 uppercase">
            <i class="ph ph-selection-slash text-sm"></i> Which hours were affected?
        </p>
        <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Leave unselected to {{ $verb }} the whole session. Tap just the affected hour(s) to {{ $verb }} only those — the rest stays booked as-is.</p>

        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
            <template x-for="slot in allBookingSlots" :key="slot.id">
                <button
                    type="button"
                    @click="toggleAffected(slot.id)"
                    class="rounded-lg border px-2 py-2.5 text-xs font-semibold transition-all"
                    :class="affected[slot.id] ? 'border-rose-500 bg-rose-500 text-white shadow-sm scale-[1.02]' : 'border-ink-200 bg-ink-50 text-ink-600 hover:border-rose-300 hover:bg-rose-50 dark:border-ink-700 dark:bg-ink-950 dark:text-ink-300'"
                    x-text="slotLabel(slot)"
                ></button>
            </template>
        </div>

        <p x-show="isPartial" x-cloak class="mt-3 flex items-center gap-1.5 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:bg-rose-950 dark:text-rose-300">
            <i class="ph ph-info"></i>
            Only the <span x-text="affectedIds.length"></span> selected hour(s) will {{ $verb }} — the rest stays on this booking untouched.
        </p>

        <template x-for="id in affectedIds" :key="'affected-' + id">
            <input type="hidden" name="affected_court_slot_ids[]" :value="id">
        </template>
    </div>
@endif
