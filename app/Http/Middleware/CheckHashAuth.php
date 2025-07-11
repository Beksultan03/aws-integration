<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\AmazonAds\Models\UserAuthHash;
use App\Models\SbUser;
use Illuminate\Support\Facades\Auth;

class CheckHashAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $hash = $request->header('X-Auth-Hash');

        if (!$hash) {
            return response()->json(['error' => 'Authentication hash is required'], 401);
        }

        $authHash = UserAuthHash::where('hash', $hash)->first();

        if (!$authHash) {
            return response()->json(['error' => 'Invalid authentication hash'], 401);
        }

        $user = SbUser::where('id', $authHash->user_id)->with('pmAccessRelations')->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        Auth::setUser($user);
        $request->merge(['user' => $user]);

        return $next($request);
    }
}
