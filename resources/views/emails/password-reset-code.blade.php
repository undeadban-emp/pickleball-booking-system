@php
    $__brand = \App\Models\OperatingHours::current();
    $__logoUrl = $__brand->logoUrl();
    $__brandName = $__brand->show_brand_text ? $__brand->brand_text : config('app.name');
    $__facebookUrl = $__brand->facebook_url;
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
        <b style="font-size:20px;color:#1a1a1a;">Here's your {{ $__brandName }} Code</b>
    </td></tr>

    <tr><td align="center" style="padding:0 40px 28px;">
        <span style="font-size:14px;color:#666666;">Never share your confirmation code with anyone.</span>
    </td></tr>

    <tr><td align="center" style="padding:0 40px 40px;">
        <table cellpadding="0" cellspacing="0" style="background:#f0f4ff;border:2px solid #d0d9f0;border-radius:8px;">
            <tr><td style="padding:16px 48px;">
                <span style="font-size:32px;font-weight:bold;letter-spacing:8px;color:#1a1a1a;">{{ $code }}</span>
            </td></tr>
        </table>
    </td></tr>

    <tr><td align="center" style="padding:0 40px 28px;">
        <span style="font-size:13px;color:#999999;">This code expires in <strong>10 minutes</strong>. If you didn't request a password reset, you can safely ignore this email.</span>
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
                <a href="https://instagram.com" style="text-decoration:none;margin-left:12px;"><img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" width="20" alt="IG"></a>
                <a href="https://twitter.com" style="text-decoration:none;margin-left:12px;"><img src="https://cdn-icons-png.flaticon.com/24/733/733579.png" width="20" alt="X"></a>
                <a href="https://tiktok.com" style="text-decoration:none;margin-left:12px;"><img src="https://cdn-icons-png.flaticon.com/24/3046/3046121.png" width="20" alt="TT"></a>
                <a href="https://youtube.com" style="text-decoration:none;margin-left:12px;"><img src="https://cdn-icons-png.flaticon.com/24/1384/1384060.png" width="20" alt="YT"></a>
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
