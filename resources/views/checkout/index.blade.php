<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Payment Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
</head>
<body class="bg-gray-100">

    <div class="container mx-auto p-6">
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h1 class="text-2xl font-bold mb-4">Payment Checkout</h1>

            @if($checkout->checkout_mode == 'redirect')
                <div>
                    <p class="text-lg mb-4">You are being redirected for payment...</p>
                    <p class="text-sm text-gray-600">If you are not redirected automatically, click the button below:</p>
                    <a href="{{ $checkout->provider_checkout_response }}" 
                       class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Redirect to Payment
                    </a>
                    <script>
                        // Automatically redirect after 30 mili_seconds
                        setTimeout(function() {
                            window.location.href = "{{ $checkout->provider_checkout_response }}";
                        }, 30);
                    </script>
                </div>
            
            @elseif($checkout->checkout_mode == 'qr_code')
                <div class="text-center">
                    <p class="text-lg mb-4">Scan the QR code to complete the payment:</p>
                    <img src="{{ $checkout->provider_checkout_response }}" 
                         alt="QR Code" class="mx-auto h-64 w-64">
                </div>

            @elseif($checkout->checkout_mode == 'brCode')
                <div class="text-center">
                    <p class="text-lg mb-4">Scan the generated QR code to complete the payment:</p>
                    <div class="mx-auto" id="qrcode"></div>
                    <script>
                        // Generate the QR code using JavaScript
                        QRCode.toCanvas(document.getElementById('qrcode'), "{{ $checkout->provider_checkout_response }}", function (error) {
                            if (error) console.error(error);
                            console.log('QR code generated successfully!');
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
                            @foreach($checkout->provider_checkout_response as $field => $detail)
                                <tr>
                                    <td class="py-2 px-4 border-b font-semibold">{{ ucfirst(str_replace('_', ' ', $field)) }}</td>
                                    <td class="py-2 px-4 border-b">{{ $detail }}</td>
                                </tr>
                            @endforeach
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
