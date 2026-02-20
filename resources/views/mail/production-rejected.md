@component('mail::message')
# Hi {{ $user->name }},

Your Production tier request was not approved at this time.

**Reason:** {{ $reason }}

You can request Production access again once the requirements are met.

Thanks,
{{ config('app.name') }} Team
@endcomponent
