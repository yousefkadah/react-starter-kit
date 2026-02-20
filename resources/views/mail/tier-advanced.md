@component('mail::message')
# Hi {{ $user->name }},

Your PassKit account tier has been upgraded to **{{ $tierName }}**.

@if (!empty($nextSteps))
## Next steps
@foreach ($nextSteps as $step)
- {{ $step }}
@endforeach
@endif

@component('mail::button', ['url' => config('app.url') . '/settings'])
View Account Settings
@endcomponent

Thanks,
{{ config('app.name') }} Team
@endcomponent
