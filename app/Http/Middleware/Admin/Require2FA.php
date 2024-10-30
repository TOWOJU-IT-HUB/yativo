<?php

namespace App\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Require2FA
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('admin')->user();

        if ($user && $user->google2fa_secret && !$request->session()->has('2fa:admin:authenticated')) {
            Auth::guard('admin')->logout();
            $request->session()->put('2fa:admin:id', $user->id);
            return redirect()->route('admin.2fa.show');
        }

        return $next($request);
    }
}
