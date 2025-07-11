<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccessKey
{

    public function handle(Request $request, Closure $next): Response
    {
        $accessKey = $request->header('Access-Key');

        $configuredAccessKey = config('auth.auth_access_key');

        if ($accessKey !== $configuredAccessKey) {
            return response()->json(['error' => 'Access Key is wrong'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
