<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureAccessToken
{
    public function handle(Request $request, Closure $next)
    {
        $payload = JWTAuth::parseToken()->getPayload();

        if ($payload->get('type') !== 'access') {
            return response()->json([
                'success' => false,
                'message' => 'Access token required',
            ], 401);
        }

        return $next($request);
    }
}
