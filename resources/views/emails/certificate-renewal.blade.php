@component('mail::message')
# Certificate Renewal Required

Hello {{ $userName }},

Your Apple Wallet certificate (Fingerprint: **{{ $fingerprint }}**) will expire on **{{ $expiresAt }}**.

We've attached a new Certificate Signing Request (CSR) to help you renew your certificate.

## Renewal Instructions

{!! nl2br(e($instructions)) !!}

@component('mail::button', ['url' => config('app.url')])
View Account Settings
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
