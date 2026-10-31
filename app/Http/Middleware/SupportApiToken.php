<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token check for the REST API. Accepts a key made in Settings →
 * API keys, or SUPPORT_API_TOKEN from .env.
 */
class SupportApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $envToken = (string) config('support.api_token');

        if ($envToken !== '' && hash_equals($envToken, $token)) {
            return $next($request);
        }

        $key = ApiKey::findByToken($token);

        if ($key === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // A write per minute at most, not per request.
        if ($key->last_used_at === null || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
