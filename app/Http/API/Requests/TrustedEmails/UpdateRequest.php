<?php

namespace App\Http\API\Requests\TrustedEmails;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{

    public function rules()
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'email.required' => 'Email is required.',
            'password.required' => 'Password is required.',
        ];
    }
}
