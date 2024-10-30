@component('mail::message')
    # KYC Verification

    Dear {{ $name }},

    Please verify your KYC information for {{ $businessName }}.

    @component('mail::button', ['url' => $verificationUrl])
        Verify
    @endcomponent

    Thanks,<br>
    {{ config('app.name') }}
@endcomponent
