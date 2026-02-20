@component('mail::message')
# New Production Tier Request

A new production request has been submitted.

**User:** {{ $user->name }}
**Email:** {{ $user->email }}

@component('mail::button', ['url' => $approvalUrl])
Review Request
@endcomponent

Thanks,
{{ config('app.name') }} Team
@endcomponent
