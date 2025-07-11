<?php

namespace App\Services\SbUser;

use App\Models\SbUser;
use Illuminate\Support\Facades\Log;
class SbUserService
{

    public function updateCompany($data)
    {
        $user = SbUser::find($data['user_id']);
        $user->company_id = $data['company_id'];
        $user->save();
    }
}
