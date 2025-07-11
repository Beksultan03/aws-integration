<?php

namespace App\Http\API\Requests\WindowsKeys;

use App\Http\API\Requests\BaseRequest;
use App\Rules\WindowsKeys\OrderExists;
use App\Rules\WindowsKeys\UserExist;

class RetrieveByOrderIdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', new OrderExists()],
            'user_id' => ['required', 'integer', new UserExist()],
        ];
    }
}
