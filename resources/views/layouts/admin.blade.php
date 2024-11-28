<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>
        Zee Technology SPA
    </title>
    <link rel="icon" href="favicon.ico">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    {{-- @vite('resources/css/app.css') --}}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('css')
    {{-- <script src="//unpkg.com/alpinejs@3.14.3/dist/cdn.min.js"></script> --}}
    <meta name="description"
        content="YATIVO! Build Financial Solutions with Yativo Create and launch products that enable your users hold funds, pay bills, open bank accounts, make cross-border payments and do so much more. Join Waitlist Currently in private beta Serve users in: Launch in Days, not Months Our APIs are easy to integrate, and enables you go live in" />
    <meta name="robots" content="max-image-preview:large" />
    <meta name="google-site-verification" content="2rrP2vQIF58teB9gnoB3vqBZ2XU8x8NyIYh-NU70ZWk" />
    <link rel="canonical" href="https://yativo.com/" />
    <meta name="generator" content="All in One SEO (AIOSEO) 4.7.4.1" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:site_name" content="Yativo  - Payouts Infrastructure for Latin America" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Home - Yativo" />
    <meta property="og:description"
        content="YATIVO! Build Financial Solutions with Yativo Create and launch products that enable your users hold funds, pay bills, open bank accounts, make cross-border payments and do so much more. Join Waitlist Currently in private beta Serve users in: Launch in Days, not Months Our APIs are easy to integrate, and enables you go live in" />
    <meta property="og:url" content="https://yativo.com/" />
    <meta property="og:image" content="https://yativo.com/wp-content/uploads/2024/04/Yativo-512x512-black_090554.png" />
    <meta property="og:image:secure_url"
        content="https://yativo.com/wp-content/uploads/2024/04/Yativo-512x512-black_090554.png" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="@yativodotcom" />
    <meta name="twitter:title" content="Home - Yativo" />
    <meta name="twitter:description"
        content="YATIVO! Build Financial Solutions with Yativo Create and launch products that enable your users hold funds, pay bills, open bank accounts, make cross-border payments and do so much more. Join Waitlist Currently in private beta Serve users in: Launch in Days, not Months Our APIs are easy to integrate, and enables you go live in" />
    <meta name="twitter:creator" content="@yativodotcom" />
    <meta name="twitter:image"
        content="https://yativo.com/wp-content/uploads/2024/04/Yativo-512x512-black_090554.png" />
    <meta name="robots" content="noindex,follow" />
</head>

<body x-data="{ page: 'Modals', 'loaded': true, 'darkMode': true, 'stickyMenu': false, 'sidebarToggle': false, 'scrollTop': false }" x-init="darkMode = JSON.parse(localStorage.getItem('darkMode'));
$watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))" :class="{ 'dark text-bodydark bg-boxdark-2': darkMode === true }">
    <!-- ===== Preloader Start ===== -->
    <div x-show="loaded" x-init="window.addEventListener('DOMContentLoaded', () => { setTimeout(() => loaded = false, 500) })"
        class="fixed left-0 top-0 z-999999 flex h-screen w-screen items-center justify-center bg-white dark:bg-black">
        <div class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-primary border-t-transparent">
        </div>
    </div>

    <!-- ===== Preloader End ===== -->

    <!-- ===== Page Wrapper Start ===== -->
    <div class="flex h-screen overflow-hidden">
        <!-- ===== Sidebar Start ===== -->
        @include('layouts.sidebar')
        <!-- ===== Sidebar End ===== -->

        <!-- ===== Content Area Start ===== -->
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <!-- ===== Header Start ===== -->
            @include('layouts.header')

            <!-- ===== Header End ===== -->

            <!-- ===== Main Content Start ===== -->
            <main class="m-3">
                <div class="container mx-auto px-4 py-2">
                    @include('layouts.alerts')
                </div>
                @yield('content')
            </main>
            <!-- ===== Main Content End ===== -->
        </div>
        <!-- ===== Content Area End ===== -->
    </div>
    <!-- ===== Page Wrapper End ===== -->
    <script defer src="{{ asset('assets/js/bundle.js') }}"></script>
    @stack('script')
    @stack('scripts')
</body>

</html>
