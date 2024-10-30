<?php

namespace App\Http\Middleware;

use Closure;
use Http;

class JsonRequestMiddleware
{
    public function handle($request, Closure $next)
    {
        if (auth()->check()) {
            $user = auth()->user();

            if ($user->roles->isEmpty()) {
                $user->assignRole($user->account_type);
            }

        }

        // Http::get(url('generate-ref-accounts'));
        
        $request->headers->add(['Accept' => 'application/json']);
        return $next($request);
    }
}

