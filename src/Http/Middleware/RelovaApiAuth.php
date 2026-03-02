<?php

declare(strict_types=1);

namespace Relova\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Relova\Models\RelovaApiKey;

/**
 * Authenticate API requests via Relova API keys.
 * Looks for Bearer token in Authorization header.
 */
class RelovaApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'Authentication required',
                'message' => 'Provide a valid API key in the Authorization header as Bearer token.',
            ], 401);
        }

        $apiKey = RelovaApiKey::findByKey($token);

        if (! $apiKey) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is invalid or expired.',
            ], 401);
        }

        $apiKey->recordUsage();

        // Store the API key and tenant context on the request
        $request->attributes->set('relova_api_key', $apiKey);
        $request->attributes->set('relova_tenant_id', $apiKey->tenant_id);

        return $next($request);
    }
}
