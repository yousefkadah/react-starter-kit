@component('mail::message')
# Hi {{ $user->name }},

Great news! Your Production tier request has been approved.

## Features unlocked
@foreach ($features as $feature)
- {{ $feature }}
@endforeach

@component('mail::button', ['url' => config('app.url') . '/settings'])
View Account Settings
@endcomponent

Thanks,
{{ config('app.name') }} Team
@endcomponent
