<?php

namespace App\Http\API\Requests\TrustedEmails;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{

    public function rules()
    {
        return [
            'email' => 'required|email|unique:tbl_trusted_emails,email',
            'password' => 'required|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'name' => 'Please provide a name.',
            'email.required' => 'This email is required.',
            'email.unique' => 'This email has already been registered.',
        ];
    }
}
