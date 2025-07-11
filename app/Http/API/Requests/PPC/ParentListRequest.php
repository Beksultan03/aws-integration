<?php

namespace App\Http\API\Requests\PPC;

use Illuminate\Foundation\Http\FormRequest;

class ParentListRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'page' => 'required|integer|min:1',
            'per_page' => 'required|integer|min:1|max:100',
        ];
    }

    public function messages()
    {
        return [
            'page.required' => 'A page number is required.',
            'page.integer' => 'The page number must be exactly that number.',
            'page.min' => 'The minimum page value is 1.',
            'per_page.required' => 'The number of posts per page is required.',
            'per_page.integer' => 'The number of posts must be an integer.',
            'per_page.min' => 'The minimum number of posts is 1.',
            'per_page.max' => 'The maximum number of posts is 100.',
        ];
    }
}
