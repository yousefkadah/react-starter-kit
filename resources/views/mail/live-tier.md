@component('mail::message')
# Hi {{ $user->name }},

Your PassKit account is now **LIVE**.

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
