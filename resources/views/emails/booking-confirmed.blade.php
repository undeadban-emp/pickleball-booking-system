@php
    $__brand = \App\Models\OperatingHours::current();
    $__logoUrl = $__brand->logoUrl();
    $__brandName = $__brand->show_brand_text ? $__brand->brand_text : config('app.name');
    $__facebookUrl = $__brand->facebook_url;
    $__slots = $booking->slots->sortBy('start_time')->values();
    $__qrUrl = $booking->checkinQrUrl();
@endphp
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f2f4f7;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f7;padding:30px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">

    <tr><td align="center" style="padding:40px 32px 20px;">
        @if ($__logoUrl)
            <img src="{{ $__logoUrl }}" alt="{{ $__brandName }}" width="180" style="display:block;max-width:180px;height:auto;">
        @else
            <span style="font-size:22px;font-weight:bold;color:#111111;">{{ $__brandName }}</span>
        @endif
    </td></tr>

    <tr><td align="center" style="padding:10px 32px 0;">
        <h1 style="margin:0;color:#111111;font-size:24px;font-weight:700;">Your Booking is Approved</h1>
        <p style="margin:10px 0 0;color:#6b7280;font-size:14px;">Show the QR code below at check-in.</p>
    </td></tr>

    <tr><td style="padding:28px 40px 0;">
        <p style="margin:0;font-size:15px;color:#333333;line-height:1.6;">
            Hi <strong>{{ $booking->contactName() }}</strong>, great news — your pickleball court booking has been reviewed and approved.
        </p>
    </td></tr>

    <tr><td style="padding:20px 40px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8faf9;border:1px solid #e3e8e6;border-radius:10px;">
            <tr><td style="padding:20px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#333333;">
                    <tr>
                        <td style="padding:6px 0;color:#6b7280;width:35%;">Reference</td>
                        <td style="padding:6px 0;font-weight:600;text-align:right;">{{ $booking->booking_code }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#6b7280;">Court</td>
                        <td style="padding:6px 0;font-weight:600;text-align:right;">{{ $booking->court->name }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#6b7280;">Customer</td>
                        <td style="padding:6px 0;font-weight:600;text-align:right;">{{ $booking->contactName() }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#6b7280;">Email</td>
                        <td style="padding:6px 0;font-weight:600;text-align:right;">{{ $booking->contactEmail() }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#6b7280;">Status</td>
                        <td style="padding:6px 0;text-align:right;">
                            <span style="display:inline-block;background:#e6f7ee;color:#0F9D58;font-size:12px;font-weight:700;padding:3px 10px;border-radius:12px;">CONFIRMED</span>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:16px 40px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8faf9;border:1px solid #e3e8e6;border-radius:10px;">
            <tr><td style="padding:20px 24px;">
                <p style="margin:0 0 12px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Schedule</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#333333;">
                    @foreach ($__slots as $slot)
                        <tr>
                            <td style="padding:5px 0;{{ ! $loop->last ? 'border-bottom:1px solid #eef0f2;' : '' }}">
                                {{ $slot->slot_date->format('M j, Y') }}, {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} to {{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:16px 40px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eefaf3;border:1px solid #cdeedd;border-radius:10px;">
            <tr><td style="padding:16px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size:14px;color:#333333;font-weight:600;">Total</td>
                        <td align="right" style="font-size:18px;color:#0F9D58;font-weight:700;">₱{{ number_format($booking->total_price, 2) }}</td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:16px 40px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8faf9;border:1px solid #e3e8e6;border-radius:10px;">
            <tr><td style="padding:16px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td valign="middle" style="font-size:13px;color:#4b5563;">Save this link, no login needed to check back.</td>
                        <td align="right" valign="middle" style="white-space:nowrap;">
                            <a href="{{ $statusUrl }}" target="_blank" style="display:inline-block;padding:8px 16px;font-size:13px;font-weight:700;color:#0F9D58;text-decoration:none;border:1px solid #0F9D58;border-radius:6px;">Copy link</a>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </td></tr>

    @if ($__qrUrl)
        <tr><td align="center" style="padding:32px 40px 0;">
            <h2 style="margin:0 0 6px;color:#111111;font-size:18px;font-weight:700;">Show this code at the gate</h2>
            <p style="margin:0 0 20px;font-size:13px;color:#6b7280;line-height:1.6;">Show this QR code to the receptionist when you arrive — no need for a data connection.</p>
            <table role="presentation" cellpadding="0" cellspacing="0" style="background:#eef2fb;border-radius:12px;">
                <tr><td style="padding:20px;">
                    <img src="{{ $__qrUrl }}" width="200" height="200" alt="Booking QR Code" style="display:block;width:200px;height:200px;border-radius:6px;">
                </td></tr>
            </table>
            <p style="margin:14px 0 4px;font-size:13px;color:#9ca3af;">Booking Ref: <strong style="color:#333333;">{{ $booking->booking_code }}</strong></p>
            @if ($booking->checkin_token_expires_at)
                <p style="margin:0 0 8px;font-size:12px;color:#c1c7cd;">Valid until {{ $booking->checkin_token_expires_at->format('g:i A, M j') }}</p>
            @endif
            <p style="margin:0;font-size:12px;color:#9ca3af;">Code not showing? <a href="{{ $statusUrl }}" style="color:#0F9D58;">View it on your booking page</a>.</p>
        </td></tr>
    @endif

    <tr><td align="center" style="padding:16px 40px 0;">
        <table role="presentation" cellpadding="0" cellspacing="0">
            <tr><td>
                <a href="{{ $statusUrl }}" target="_blank" style="display:inline-block;padding:12px 32px;font-size:14px;font-weight:700;color:#0F9D58;text-decoration:none;border:1px solid #0F9D58;border-radius:8px;">View Booking Details</a>
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:24px 40px 0;">
        <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.6;text-align:center;">
            Please arrive at least 10 minutes before your scheduled time. Bookings not checked in within 15 minutes may be released.
        </p>
    </td></tr>

    <tr><td style="padding:36px 40px 0;"><hr style="border:none;border-top:1px solid #eef0f2;margin:0;"></td></tr>

    <tr><td style="padding:24px 40px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td align="left" valign="middle">
                    @if ($__logoUrl)
                        <img src="{{ $__logoUrl }}" alt="{{ $__brandName }}" height="32" style="display:block;height:32px;width:auto;">
                    @else
                        <span style="font-size:14px;font-weight:bold;color:#1a1a1a;">{{ $__brandName }}</span>
                    @endif
                </td>
                <td align="right" valign="middle">
                    @if ($__facebookUrl)
                        <a href="{{ $__facebookUrl }}" style="text-decoration:none;margin-left:8px;"><img src="https://cdn-icons-png.flaticon.com/24/733/733547.png" width="20" alt="FB"></a>
                    @endif
                    {{-- <a href="https://instagram.com" style="text-decoration:none;margin-left:8px;"><img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" width="20" alt="IG"></a>
                    <a href="https://twitter.com" style="text-decoration:none;margin-left:8px;"><img src="https://cdn-icons-png.flaticon.com/24/733/733579.png" width="20" alt="X"></a>
                    <a href="https://tiktok.com" style="text-decoration:none;margin-left:8px;"><img src="https://cdn-icons-png.flaticon.com/24/3046/3046121.png" width="20" alt="TT"></a>
                    <a href="https://youtube.com" style="text-decoration:none;margin-left:8px;"><img src="https://cdn-icons-png.flaticon.com/24/1384/1384060.png" width="20" alt="YT"></a> --}}
                </td>
            </tr>
        </table>
    </td></tr>

    <tr><td align="center" style="padding:20px 40px 36px;">
        <p style="margin:0;font-size:13px;color:#9ca3af;">{{ $__brandName }}</p>
        <p style="margin:8px 0 0;font-size:12px;color:#c1c7cd;">
            Need help? <a href="mailto:{{ config('mail.from.address') }}" style="color:#0F9D58;text-decoration:none;">{{ config('mail.from.address') }}</a> &nbsp;|&nbsp; &copy; {{ date('Y') }} {{ $__brandName }}
        </p>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
