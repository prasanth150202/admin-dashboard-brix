<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bearer-token check for the chatbot API. No token configured means the API is off. */
class SupportApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('support.api_token');

        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
