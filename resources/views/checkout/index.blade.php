@if ($checkout->checkout_mode == 'onramp')
    @include('welcome', ['checkout' => $checkout])
@else
    @include('checkout.checkout', ['checkout' => $checkout])
@endif
