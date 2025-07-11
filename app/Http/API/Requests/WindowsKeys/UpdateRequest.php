<?php

namespace App\Http\API\Requests\WindowsKeys;

use App\Http\API\Requests\BaseRequest;
use App\Rules\WindowsKeys\UserExist;

class UpdateRequest extends BaseRequest
{

    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'user_id' => ['required', 'integer', new UserExist()],
            'need_to_download_new_keys' => 'boolean',
        ];
    }
}
