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
        <h1 style="margin:0;color:#111111;font-size:24px;font-weight:700;">Booking Not Approved</h1>
        <p style="margin:10px 0 0;color:#6b7280;font-size:14px;">We're unable to confirm this reservation.</p>
    </td></tr>

    <tr><td style="padding:28px 40px 0;">
        <p style="margin:0;font-size:15px;color:#333333;line-height:1.6;">
            Hi <strong>{{ $booking->contactName() }}</strong>, we're sorry, but your pickleball court booking request could not be approved at this time. Details of the request are below.
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
                            <span style="display:inline-block;background:#fdeaea;color:#E53935;font-size:12px;font-weight:700;padding:3px 10px;border-radius:12px;">REJECTED</span>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:16px 40px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8faf9;border:1px solid #e3e8e6;border-radius:10px;">
            <tr><td style="padding:20px 24px;">
                <p style="margin:0 0 12px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Requested Schedule</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#333333;">
                    @foreach ($__slots as $slot)
                        <tr>
                            <td style="padding:5px 0;{{ ! $loop->last ? 'border-bottom:1px solid #eef0f2;' : '' }}text-decoration:line-through;color:#9ca3af;">
                                {{ $slot->slot_date->format('M j, Y') }}, {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} to {{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:20px 40px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff6f6;border:1px solid #f3d3d3;border-radius:10px;">
            <tr><td style="padding:16px 20px;">
                <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#E53935;text-transform:uppercase;letter-spacing:0.5px;">Reason</p>
                <p style="margin:0;font-size:14px;color:#4b5563;line-height:1.6;">
                    {{ $booking->rejection_reason ?: 'No specific reason was provided. Please contact us if you have questions.' }}
                </p>
            </td></tr>
        </table>
    </td></tr>

    <tr><td align="center" style="padding:32px 40px 0;">
        <table role="presentation" cellpadding="0" cellspacing="0">
            <tr><td style="background:#0F9D58;border-radius:8px;">
                <a href="{{ $rebookUrl }}" target="_blank" style="display:inline-block;padding:13px 32px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;">Book Another Slot</a>
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:24px 40px 0;">
        <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.6;text-align:center;">
            If you believe this was a mistake, please reach out to our support team and we'll be glad to help.
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
