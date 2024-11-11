<x-guest-layout>
    <div class="min-h-screen bg-gray-100 text-gray-900 flex justify-center items-center">
        <div class="max-w-7xl w-full mx-auto">
            <div class="bg-white shadow-2xl sm:rounded-lg grid grid-cols-1 lg:grid-cols-2 overflow-hidden">
                <!-- Left Column - Image -->
                <div class="hidden lg:block bg-indigo-600 relative">
                    <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/90 to-purple-500/90"></div>
                    <div class="relative h-full flex items-center justify-center p-12">
                        <div class="text-center text-white space-y-6">
                            <h2 class="text-4xl font-bold">Welcome to Yativo</h2>
                            <p class="text-xl text-indigo-100">Your trusted platform for efficient management</p>
                            <img src="https://storage.googleapis.com/devitary-image-host.appspot.com/15848031292911696601-undraw_designer_life_w96d.svg" 
                                 class="max-w-md mx-auto" alt="Welcome illustration">
                        </div>
                    </div>
                </div>

                <!-- Right Column - Login Form -->
                <div class="p-8 lg:p-12 flex items-center justify-center">
                    <div class="w-full max-w-md space-y-8">
                        <div class="text-center">
                            <x-authentication-card-logo class="mx-auto h-12 w-auto" />
                            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Welcome back</h2>
                            <p class="mt-2 text-sm text-gray-600">Sign in to your account</p>
                        </div>

                        <x-validation-errors class="mb-4" />

                        @if (session('status'))
                            <div class="mb-4 font-medium text-sm text-green-600">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" class="space-y-6">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                                    <input id="email" name="email" type="email" required placeholder="user@username.com" 
                                           class="mt-1 block w-full px-4 py-3 font-mono rounded-lg border border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                </div>

                                <div class="relative">
                                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                                    <input id="password" name="password" type="password" required placeholder="MySecretPassword.com" 
                                           class="mt-1 block w-full px-4 py-3 font-mono rounded-lg border border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                    <button type="button" onclick="togglePasswordVisibility()" 
                                            class="absolute right-3 top-[60%] transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <svg class="h-5 w-5" id="togglePassword" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="flex items-center justify-between">
                                    <label class="flex items-center">
                                        <x-checkbox id="remember_me" name="remember" />
                                        <span class="ml-2 text-sm text-gray-600">Remember me</span>
                                    </label>
                                </div>

                                <button type="submit" 
                                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Sign in
                                </button>
                            </div>
                        </form>

                        <p class="mt-6 text-center text-sm text-gray-600">
                            Welcome to the admin dashboard for Yativo staffs
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;
            } else {
                passwordInput.type = 'password';
                toggleIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />`;
            }
        }
    </script>
</x-guest-layout>
