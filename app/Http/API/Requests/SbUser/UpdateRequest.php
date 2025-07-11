<?php

namespace App\Http\API\Requests\SbUser;

use App\Http\API\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:tbl_sb_user,id'],
            'company_id' => ['required', 'integer', 'exists:tbl_company,company_id'],
        ];
    }
}

