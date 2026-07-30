@php
    $__brand = \App\Models\OperatingHours::current();
    $__logoUrl = $__brand->logoUrl();
    $__brandName = $__brand->show_brand_text ? $__brand->brand_text : config('app.name');
    $__facebookUrl = $__brand->facebook_url;
    $__slots = $booking->slots->sortBy('start_time')->values();
@endphp
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 0;">
<tr><td align="center">
<table width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;">

    <tr><td align="center" style="padding:40px 40px 24px;">
        @if ($__logoUrl)
            <img src="{{ $__logoUrl }}" alt="{{ $__brandName }}" width="150" style="display:block;">
        @else
            <span style="font-size:22px;font-weight:bold;color:#1a1a1a;">{{ $__brandName }}</span>
        @endif
    </td></tr>

    <tr><td align="center" style="padding:0 40px 12px;">
        <b style="font-size:20px;color:#1a1a1a;">New Payment Submitted</b>
    </td></tr>

    <tr><td align="center" style="padding:0 40px 28px;">
        <span style="font-size:14px;color:#666666;">A customer just submitted proof of payment. This booking is awaiting your approval.</span>
    </td></tr>

    <tr><td style="padding:0 40px 20px;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;border:2px solid #d0d9f0;border-radius:8px;">
            <tr><td style="padding:18px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#333333;">
                    <tr>
                        <td style="padding:5px 0;color:#666666;width:40%;">Reference</td>
                        <td style="padding:5px 0;font-weight:bold;text-align:right;">{{ $booking->booking_code }}</td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0;color:#666666;">Court</td>
                        <td style="padding:5px 0;font-weight:bold;text-align:right;">{{ $booking->court->name }}</td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0;color:#666666;">Customer</td>
                        <td style="padding:5px 0;font-weight:bold;text-align:right;">{{ $booking->contactName() }}</td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0;color:#666666;">Email</td>
                        <td style="padding:5px 0;font-weight:bold;text-align:right;">{{ $booking->contactEmail() }}</td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0;color:#666666;">GCash Reference</td>
                        <td style="padding:5px 0;font-weight:bold;text-align:right;">{{ $booking->gcash_reference ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0;color:#666666;">Submitted</td>
                        <td style="padding:5px 0;font-weight:bold;text-align:right;">{{ $booking->gcash_submitted_at?->format('M j, g:i A') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0;color:#666666;">Total</td>
                        <td style="padding:5px 0;font-weight:bold;text-align:right;">₱{{ number_format($booking->total_price, 2) }}</td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:0 40px 20px;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8faf9;border:1px solid #e3e8e6;border-radius:8px;">
            <tr><td style="padding:16px 20px;">
                <p style="margin:0 0 8px;font-size:11px;font-weight:bold;color:#666666;text-transform:uppercase;letter-spacing:0.5px;">Schedule</p>
                <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#333333;">
                    @foreach ($__slots as $slot)
                        <tr>
                            <td style="padding:4px 0;{{ ! $loop->last ? 'border-bottom:1px solid #eef0f2;' : '' }}">
                                {{ $slot->slot_date->format('M j, Y') }}, {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} to {{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td></tr>
        </table>
    </td></tr>

    <tr><td align="center" style="padding:0 40px 32px;">
        <table cellpadding="0" cellspacing="0">
            <tr><td style="background:#1a1a1a;border-radius:8px;">
                <a href="{{ $reviewUrl }}" target="_blank" style="display:inline-block;padding:13px 32px;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;">Review in Admin Panel</a>
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:0 40px;"><hr style="border:none;border-top:1px solid #e8e8e8;margin:0;"></td></tr>

    <tr><td style="padding:28px 40px 12px;">
        <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="left" valign="middle">
                @if ($__logoUrl)
                    <img src="{{ $__logoUrl }}" alt="{{ $__brandName }}" width="50" style="display:inline-block;vertical-align:middle;">
                @else
                    <span style="font-size:14px;font-weight:bold;color:#1a1a1a;">{{ $__brandName }}</span>
                @endif
            </td>
            <td align="right" valign="middle">
                @if ($__facebookUrl)
                    <a href="{{ $__facebookUrl }}" style="text-decoration:none;margin-left:12px;"><img src="https://cdn-icons-png.flaticon.com/24/733/733547.png" width="20" alt="FB"></a>
                @endif
                {{-- <a href="https://instagram.com" style="text-decoration:none;margin-left:12px;"><img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" width="20" alt="IG"></a>
                <a href="https://twitter.com" style="text-decoration:none;margin-left:12px;"><img src="https://cdn-icons-png.flaticon.com/24/733/733579.png" width="20" alt="X"></a>
                <a href="https://tiktok.com" style="text-decoration:none;margin-left:12px;"><img src="https://cdn-icons-png.flaticon.com/24/3046/3046121.png" width="20" alt="TT"></a>
                <a href="https://youtube.com" style="text-decoration:none;margin-left:12px;"><img src="https://cdn-icons-png.flaticon.com/24/1384/1384060.png" width="20" alt="YT"></a> --}}
            </td>
        </tr>
        </table>
    </td></tr>

    <tr><td align="center" style="padding:4px 40px 32px;">
        <span style="font-size:13px;color:#999999;">{{ $__brandName }}</span>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
