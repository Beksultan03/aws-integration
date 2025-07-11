<?php

namespace App\AmazonAds\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\API\Controllers\BaseController;
use App\AmazonAds\Models\UserAuthHash;

class AuthController extends BaseController
{
    public function validateHash(Request $request)
    {
        $hash = $request->query('hash');

        $authHash = UserAuthHash::where('hash', $hash)->first();

        if (!$authHash) {
            return response()->json(['error' => 'Invalid hash'], 401);
        }

        $user = $authHash->user;

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->fname,
                'last_name' => $user->lname,
                'email' => $user->email,
                'image' => $user->image,
                'is_supervisor' => $user->is_supervisor,
                'company' => [
                    'id' => $user?->company_id,
                    'name' => $user?->company?->name,
                ],
                'accessRelations' => $user->pmAccessRelations,
            ],
            'hash' => $hash
        ]);
    }
}