<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Payment Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script> <!-- QRCode.js -->
</head>

<body class="bg-gray-100">

    <div class="container mx-auto p-6">
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h1 class="text-2xl font-bold mb-4">Payment Checkout</h1>

            @if ($checkout->checkout_mode == 'redirect')
                <div>
                    <p class="text-lg mb-4">You are being redirected for payment...</p>
                    <p class="text-sm text-gray-600">If you are not redirected automatically, click the button below:
                    </p>
                    <a href="{{ is_string($checkout->provider_checkout_response) ? $checkout->provider_checkout_response : $checkout->provider_checkout_response['url'] }}"
                        class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Redirect to Payment
                    </a>
                    <script>
                        // Automatically redirect after 300 milliseconds
                        setTimeout(function() {
                            window.location.href =
                                "{{ is_string($checkout->provider_checkout_response) ? $checkout->provider_checkout_response : $checkout->provider_checkout_response['url'] }}";
                        }, 300);
                    </script>
                </div>
            @elseif($checkout->checkout_mode == 'qr_code')
                <div class="text-center">
                    <p class="text-lg mb-4">Scan the QR code to complete the payment:</p>
                    <img src="{{ is_string($checkout->provider_checkout_response) ? $checkout->provider_checkout_response : $checkout->provider_checkout_response['qr_code'] }}"
                        alt="QR Code" class="mx-auto h-64 w-64">

                    <div id="qrcode" class="mx-auto"></div>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            // Ensure provider_checkout_response is a string for QR code generation
                            const qrData =
                                "{{ is_string($checkout->provider_checkout_response) ? $checkout->provider_checkout_response : $checkout->provider_checkout_response['brCode'] }}";
                            if (qrData) {
                                // Generate the QR code using QRCode.js
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
                            // Ensure provider_checkout_response is a string for QR code generation
                            const qrData =
                                "{{ is_string($checkout->provider_checkout_response) ? $checkout->provider_checkout_response : $checkout->provider_checkout_response['brCode'] }}";
                            if (qrData) {
                                // Generate the QR code using QRCode.js
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
            @else
                <p class="text-lg text-red-600">Invalid payment method.</p>
            @endif
        </div>
    </div>

</body>

</html>
