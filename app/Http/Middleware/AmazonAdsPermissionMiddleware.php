<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\PmAccessRelation;
use Symfony\Component\HttpFoundation\Response;

class AmazonAdsPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission  The minimum required permission level (read, write, admin)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission = 'read')
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], Response::HTTP_UNAUTHORIZED);
        }
        
        $permissionValue = [1, 2];
        $amazonPermissionId = 36;

        $hasAccess = PmAccessRelation::where('user_id', $user->id)
            ->whereHas('pmAccess', function ($query) use ($amazonPermissionId) {
                $query->where('access_id', $amazonPermissionId);
            })
            ->whereIn('permission_id', $permissionValue)
            ->exists();
            
        if (!$hasAccess) {
            return response()->json([
                'error' => 'You do not have sufficient permissions for Amazon Ads'
            ], Response::HTTP_FORBIDDEN);
        }
        
        return $next($request);
    }
} 