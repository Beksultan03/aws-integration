<?php

namespace App\Http\API\Requests\WindowsKeys;

use App\Http\API\Requests\BaseRequest;
use App\Rules\WindowsKeys\UserExist;

class SendToManufacturerRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'user_id' => ['required', 'integer', new UserExist()],
        ];
    }
}
