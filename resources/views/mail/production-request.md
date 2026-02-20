@component('mail::message')
# Hi {{ $user->name }},

We've received your request for Production tier access. Our team will review it within 24 hours.

We'll email you as soon as a decision is made.

Thanks,
{{ config('app.name') }} Team
@endcomponent
