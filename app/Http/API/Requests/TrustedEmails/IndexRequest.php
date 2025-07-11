<?php

namespace App\Http\API\Requests\TrustedEmails;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{

    public function rules()
    {
        return [
            'email' => 'required|email:tbl_trusted_emails,email',
        ];
    }

    public function messages()
    {
        return [
            'email' => 'Email does not exist.',
        ];
    }
}
