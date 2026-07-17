<x-mail::message>
# You're confirmed

Hi {{ $booking->contactName() }}, your court is booked.

<x-mail::panel>
**{{ $booking->court->name }}**

@foreach ($booking->slots->sortBy('start_time') as $slot)
{{ \Illuminate\Support\Carbon::parse($slot->slot_date)->format('M j, Y') }}, {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} to {{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }}
@endforeach

Total paid: ₱{{ number_format($booking->total_price, 2) }}
</x-mail::panel>

Show your check-in code at the gate when you arrive. You can find it, and your booking status, at the link below.

<x-mail::button :url="$statusUrl">
View my booking
</x-mail::button>

Booking code: {{ $booking->booking_code }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
