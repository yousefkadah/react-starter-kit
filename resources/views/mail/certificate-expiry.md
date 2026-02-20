@component('mail::message')
# Hi {{ $user->name }},

@if ($daysRemaining === 0)
Your Apple Wallet certificate has **expired**.
@else
Your Apple Wallet certificate will expire in **{{ $daysRemaining }} days**.
@endif

**Expiry Date:** {{ $certificate->expiry_date->toFormattedDateString() }}

@if ($daysRemaining === 0)
Please renew your certificate immediately to avoid service interruption.
@else
We recommend renewing your certificate now to avoid any service interruption.
@endif

@component('mail::button', ['url' => $manageUrl])
Manage Certificates
@endcomponent

Thanks,
{{ config('app.name') }} Team
@endcomponent
