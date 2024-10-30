@component('mail::message')
# Your Magic Link

Click the button below to log in to your account:

Your One-time Password is: {{ $magicPassword }}

@component('mail::button', ['url' => $magicLink])
Log In
@endcomponent

If you did not request this login link, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent