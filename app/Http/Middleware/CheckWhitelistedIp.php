<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\WhitelistedIp;
use Illuminate\Support\Facades\Log;
class CheckWhitelistedIp
{
    public function handle(Request $request, Closure $next)
    {
        $currentIp = $request->ip();

        $whitelistedIpsExist = WhitelistedIp::query()->where('ip_address', $currentIp)->exists();

        if (!$whitelistedIpsExist) {
            return response()->json(['message' => 'IP address is not trusted.'], 403);
        }

        return $next($request);
    }
}

