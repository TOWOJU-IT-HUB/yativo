<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Payment Checkout</title>
    <link href="//cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="//cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script> <!-- QRCode.js -->
    <style>
        /* Loader Styles */
        .loader {
            border: 4px solid #f3f3f3;
            border-radius: 50%;
            border-top: 4px solid #3498db;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
    <script type="module">
        import {
            OnrampWebSDK
        } from '//cdn.skypack.dev/@onramp.money/onramp-web-sdk';
        window.OnrampWebSDK = OnrampWebSDK;
    </script>
</head>

<body class="bg-gray-100">

    <div class="container mx-auto p-6">
        <div class="bg-white shadow-lg rounded-lg p-6 relative">
            <h1 class="text-2xl font-bold mb-4">Payment Checkout</h1>

            @if ($checkout->checkout_mode == 'redirect')
                <div>
                    <div id="loader-container" class="text-center">
                        <p class="text-lg mb-4">You are being redirected for payment...</p>
                        <div class="loader"></div>
                        <p class="text-sm text-gray-600 mt-4">
                            If the page doesn't redirect automatically, a manual redirect option will appear in 1
                            minute.
                        </p>
                    </div>

                    <div id="manual-redirect" class="hidden text-center mt-6">
                        <p class="text-lg text-red-600">The page did not redirect automatically.</p>
                        <p class="text-sm text-gray-600 mb-4">Click the button below to continue manually:</p>
                        <a href="{!! is_string($checkout->provider_checkout_response)
                            ? str_replace('&amp;', '&', $checkout->provider_checkout_response)
                            : str_replace('&amp;', '&', $checkout->provider_checkout_response['url']) !!}"
                            class="inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Continue to Payment
                        </a>
                    </div>

                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            // Redirect after 300 milliseconds
                            setTimeout(function() {
                                window.location.href =
                                    "{!! is_string($checkout->provider_checkout_response)
                                        ? str_replace('&amp;', '&', $checkout->provider_checkout_response)
                                        : str_replace('&amp;', '&', $checkout->provider_checkout_response['url']) !!}";
                            }, 300);

                            // Show manual redirect option after 1 minute (60 seconds)
                            setTimeout(function() {
                                document.getElementById("loader-container").classList.add("hidden");
                                document.getElementById("manual-redirect").classList.remove("hidden");
                            }, 60000);
                        });
                    </script>
                </div>
            @elseif($checkout->checkout_mode == 'qr_code')
                <div class="text-center">
                    <p class="text-lg mb-4">Scan the QR code to complete the payment:</p>
                    <img src="{{ is_string($checkout->provider_checkout_response)
                        ? $checkout->provider_checkout_response
                        : $checkout->provider_checkout_response['qr_code'] }}"
                        alt="QR Code" class="mx-auto h-64 w-64">

                    <div id="qrcode" class="mx-auto"></div>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            const qrData =
                                "{{ is_string($checkout->provider_checkout_response)
                                    ? $checkout->provider_checkout_response
                                    : $checkout->provider_checkout_response['brCode'] }}";
                            if (qrData) {
                                new QRCode(document.getElementById("qrcode"), {
                                    text: qrData,
                                    width: 256,
                                    height: 256
                                });
                            } else {
                                console.error("No QR data available to generate the QR code.");
                            }
                        });
                    </script>
                </div>
            @elseif($checkout->checkout_mode == 'brCode')
                <div class="text-center">
                    <p class="text-lg mb-4">Scan the generated QR code to complete the payment:</p>
                    <div id="qrcode" class="mx-auto"></div>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            const qrData =
                                "{{ is_string($checkout->provider_checkout_response)
                                    ? $checkout->provider_checkout_response
                                    : $checkout->provider_checkout_response['brCode'] }}";
                            if (qrData) {
                                new QRCode(document.getElementById("qrcode"), {
                                    text: qrData,
                                    width: 256,
                                    height: 256
                                });
                            } else {
                                console.error("No QR data available to generate the QR code.");
                            }
                        });
                    </script>
                </div>
            @elseif($checkout->checkout_mode == 'wire_details')
                <div class="overflow-x-auto">
                    <p class="text-lg mb-4">Use the wire details below to complete the payment:</p>
                    <table class="min-w-full bg-white shadow-md rounded-lg">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 border-b">Field</th>
                                <th class="py-2 px-4 border-b">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (is_array($checkout->provider_checkout_response))
                                @foreach ($checkout->provider_checkout_response as $field => $detail)
                                    <tr>
                                        <td class="py-2 px-4 border-b font-semibold">
                                            {{ ucfirst(str_replace('_', ' ', $field)) }}</td>
                                        <td class="py-2 px-4 border-b">
                                            {{ is_array($detail) ? implode(', ', $detail) : $detail }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td class="py-2 px-4 border-b" colspan="2">No wire details available.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @elseif($checkout->checkout_mode == 'onramp')
                @include('welcome')
            @else
                <p class="text-lg text-red-600">Invalid payment method.</p>
            @endif
        </div>
    </div>

</body>

</html>
