<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <script type="module">
        import {
            OnrampWebSDK
        } from 'https://cdn.skypack.dev/@onramp.money/onramp-web-sdk';
        window.OnrampWebSDK = OnrampWebSDK;
    </script>

    <!-- Styles / Scripts -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
    @endif
</head>

<body class="font-sans antialiased dark:bg-black dark:text-white/50">
    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        <img id="background" class="absolute -left-20 top-0 max-w-[877px]"
            src="https://laravel.com/assets/img/welcome/background.svg" alt="Laravel background" />
        <div
            class="relative min-h-screen flex flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white">
            <script type="module">
                import {
                    OnrampWebSDK
                } from 'https://cdn.skypack.dev/@onramp.money/onramp-web-sdk';

                document.addEventListener('DOMContentLoaded', () => {
                    const onrampInstance = new window.OnrampWebSDK({
                        appId: 836386,
                        walletAddress: '0x495f519017eF0368e82Af52b4B64461542a5430B',
                        coinCode: 'usdt',
                        network: 'matic20',
                        fiatAmount: 1000,
                        fiatType: 6,
                        phoneNumber: '%2B90-9993749865',
                        lang: 'en',
                        assetDescription: 'CustomAsset',
                        assetImage: '//i.insider.com/6123e085de5f560019e85771?width=300',
                        flowType: 3,
                        merchantRecognitionId: '13422',
                        paymentMethod: 1, //  1 -> Instant transfer, 2 -> Bank transfer
                        redirectUrl: 'https://app.yativo.com',
                    });

                    // Show the widget
                    onrampInstance.show();

                    // Bind events to the SDK instance
                    // Listen to all the events of transaction stages
                    onrampInstance.on('TX_EVENTS', (e) => {
                        console.log('onrampInstance TX_EVENTS', e);
                    });

                    // Listen to all the events of widget stages
                    onrampInstance.on('WIDGET_EVENTS', (e) => {
                        console.log('onrampInstance WIDGET_EVENTS', e);
                    });
                });
            </script>

        </div>
    </div>
</body>

</html>
