<x-mail::message>
# Reset your password

We received a request to reset the password for your account. Use the code below to continue.

<x-mail::panel>
<div style="text-align: center; font-size: 32px; font-weight: 700; letter-spacing: 8px;">
{{ $code }}
</div>
</x-mail::panel>

This code expires in **10 minutes**. If you didn't request a password reset, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
