<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Proceed with the request to get the response
        $response = $next($request);

        // Clean and remove duplicate headers
        $sanitizedHeaders = [];
        
        foreach ($response->headers->all() as $key => $values) {
            foreach ($values as $value) {
                // Remove newlines or invalid characters from the header value
                $sanitizedValue = preg_replace("/\r|\n/", '', $value);
                
                // Store the sanitized value, overriding duplicates
                $sanitizedHeaders[$key] = $sanitizedValue;
            }
        }

        // Set the sanitized headers, ensuring no duplicates
        foreach ($sanitizedHeaders as $key => $value) {
            $response->headers->set($key, $value, false);
        }

        return $response;
    }
}
